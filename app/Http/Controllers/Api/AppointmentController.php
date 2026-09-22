<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentType;
use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Services\AppointmentConflictService;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\Clinical\AppointmentQueryService;
use App\Services\TreatmentChargeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected AppointmentQueryService $appointmentQuery,
    ) {}

    public function index(IndexAppointmentRequest $request)
    {
        $appointments = $this->appointmentQuery->list($request->user(), $request->validated());

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $chargeItems = $data['charge_items'] ?? [];
        unset($data['charge_items']);

        $this->assertActingDoctorOwnsDoctorId($request, $data['doctor_id']);
        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
        // Naming yourself as doctor_id isn't enough: without this a doctor
        // could book (and bill + write clinical notes on) a patient another
        // doctor has claimed. Deliberately the "not someone else's" check
        // rather than full ownership -- this endpoint is also how an
        // unclaimed patient first gets claimed, via ensureEnrolled() below.
        $this->assertClientNotClaimedByAnotherDoctor($request, $this->resolveClient($data['client_id'] ?? null));
        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], (int) $data['duration_minutes']);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], (int) $data['duration_minutes']);

        $appointment = Appointment::create([
            ...$data,
            'status' => $data['status'] ?? 'scheduled',
            'client_id' => $data['type'] === AppointmentType::Unavailable->value ? null : $data['client_id'],
            'end_time' => $this->conflicts->calculateEndTime($data['start_time'], (int) $data['duration_minutes']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($appointment->client_id) {
            $this->treatmentCharges->syncItems($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);
            $this->enrollment->ensureEnrolled($appointment->client, $doctor);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment created successfully.', 201);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $appointment->doctor_id);

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $appointment->doctor_id);
        $this->assertClientNotClaimedByAnotherDoctor($request, $appointment->client);

        $validated = $request->validated();
        $chargeItemsProvided = array_key_exists('charge_items', $validated);
        $chargeItems = $validated['charge_items'] ?? [];
        unset($validated['charge_items']);

        $data = [
            ...$appointment->only(['client_id', 'doctor_id', 'type', 'status', 'date', 'start_time', 'duration_minutes', 'notes']),
            ...$validated,
        ];

        $this->assertActingDoctorOwnsDoctorId($request, $data['doctor_id']);
        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
        // Re-checked against the incoming client_id too, so an appointment
        // can't be re-pointed at a colleague's patient.
        $this->assertClientNotClaimedByAnotherDoctor($request, $this->resolveClient($data['client_id'] ?? null));
        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], (int) $data['duration_minutes']);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], (int) $data['duration_minutes'], $appointment->id);

        $appointment->update([
            ...$validated,
            'client_id' => $data['type'] === AppointmentType::Unavailable->value ? null : $data['client_id'],
            'end_time' => $this->conflicts->calculateEndTime($data['start_time'], (int) $data['duration_minutes']),
            'updated_by' => $request->user()->id,
        ]);

        if ($chargeItemsProvided && $appointment->client_id) {
            // Consolidate any AI-plan-sourced charge onto this same
            // (appointment, id) bucket first -- syncItems() below only
            // deletes rows that already match that exact source, so without
            // this an AI-confirmed plan's charge and a freshly edited one
            // coexist and both get summed, double-counting the client's
            // total services.
            $this->treatmentCharges->retarget(TreatmentCharge::SOURCE_AI_PLAN, $appointment->id, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id);
            $this->treatmentCharges->syncItems($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment updated successfully.');
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $this->assertActingDoctorOwnsDoctorId($request, $appointment->doctor_id);

        $this->treatmentCharges->deleteAllForAppointment($appointment->id, $appointment->client?->company_id);
        $appointment->delete();

        return $this->success(null, 'Appointment deleted successfully.');
    }

    /**
     * find() (not exists:clients,id) so Client's BelongsToCompany global scope
     * applies -- a client_id belonging to another company resolves to null
     * here rather than sailing through as a valid patient.
     */
    protected function resolveClient(?int $clientId): ?Client
    {
        return $clientId ? Client::query()->find($clientId) : null;
    }

    protected function assertClientRules(array $data): void
    {
        if (($data['type'] ?? null) === AppointmentType::Booked->value && empty($data['client_id'])) {
            throw ValidationException::withMessages([
                'client_id' => ['The client field is required for booked appointments.'],
            ]);
        }

        if (($data['type'] ?? null) === AppointmentType::Unavailable->value && ! empty($data['client_id'])) {
            throw ValidationException::withMessages([
                'client_id' => ['The client field must be null for unavailable appointments.'],
            ]);
        }
    }
}
