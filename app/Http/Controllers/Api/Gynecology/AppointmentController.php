<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Enums\AppointmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Services\AppointmentConflictService;
use App\Services\Clinical\AppointmentQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\TreatmentChargeService;
use Illuminate\Validation\ValidationException;

/**
 * Gynevaria's own clinical Appointment endpoints. See
 * app/Http/Controllers/Api/Gynecology/ClientController.php's docblock.
 */
class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected AppointmentQueryService $appointmentQuery,
    ) {}

    public function index(IndexAppointmentRequest $request)
    {
        $appointments = $this->appointmentQuery->list([
            ...$request->validated(),
            'specialty' => 'gynecology',
        ]);

        return $this->success(AppointmentResource::collection($appointments)->response()->getData(true));
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $chargeItems = $data['charge_items'] ?? [];
        unset($data['charge_items']);

        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
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

    public function show(Appointment $appointment)
    {
        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $validated = $request->validated();
        $chargeItemsProvided = array_key_exists('charge_items', $validated);
        $chargeItems = $validated['charge_items'] ?? [];
        unset($validated['charge_items']);

        $data = [
            ...$appointment->only(['client_id', 'doctor_id', 'type', 'status', 'date', 'start_time', 'duration_minutes', 'notes']),
            ...$validated,
        ];

        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], (int) $data['duration_minutes']);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], (int) $data['duration_minutes'], $appointment->id);

        $appointment->update([
            ...$validated,
            'client_id' => $data['type'] === AppointmentType::Unavailable->value ? null : $data['client_id'],
            'end_time' => $this->conflicts->calculateEndTime($data['start_time'], (int) $data['duration_minutes']),
            'updated_by' => $request->user()->id,
        ]);

        if ($chargeItemsProvided && $appointment->client_id) {
            $this->treatmentCharges->syncItems($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment updated successfully.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->treatmentCharges->deleteAllForAppointment($appointment->id, $appointment->client?->company_id);
        $appointment->delete();

        return $this->success(null, 'Appointment deleted successfully.');
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
