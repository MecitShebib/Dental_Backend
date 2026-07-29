<?php

namespace App\Http\Controllers\Api;

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
use App\Services\TreatmentChargeService;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
    ) {}

    public function index(IndexAppointmentRequest $request)
    {
        $appointments = Appointment::query()
            ->with(['client', 'doctor'])
            ->when(request('doctor_id'), fn ($query) => $query->where('doctor_id', request('doctor_id')))
            ->when(request('client_id'), fn ($query) => $query->where('client_id', request('client_id')))
            ->when(request('status'), fn ($query) => $query->where('status', request('status')))
            ->when(
                request('date_from') && request('date_to'),
                fn ($query) => $query->whereBetween('date', [request('date_from'), request('date_to')]),
                fn ($query) => $query->when(request('date'), fn ($q) => $q->whereDate('date', request('date')))
            )
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate((int) request('per_page', 20));

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
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

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment created successfully.', 201);
    }

    public function show(Appointment $appointment)
    {
        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $validated = $request->validated();
        $chargeAmountProvided = array_key_exists('treatment_charge_amount', $validated);
        $chargeAmount = $validated['treatment_charge_amount'] ?? null;
        unset($validated['treatment_charge_amount']);

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

        if ($chargeAmountProvided && $appointment->client_id) {
            $this->treatmentCharges->sync($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeAmount);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment updated successfully.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->treatmentCharges->deleteAllForAppointment($appointment->id);
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
