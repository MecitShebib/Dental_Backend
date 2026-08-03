<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\CheckInAppointmentRequest;
use App\Http\Requests\Appointment\NoShowAppointmentRequest;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\TreatmentCharge;
use App\Models\Visit;
use App\Services\TreatmentChargeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientVisitController extends Controller
{
    public function __construct(protected TreatmentChargeService $treatmentCharges) {}

    public function index(Client $client)
    {
        $visits = $client->visits()->with(['doctor', 'appointment'])->latest('visit_date')->paginate();

        return $this->success(VisitResource::collection($visits));
    }

    public function store(StoreVisitRequest $request, Client $client)
    {
        $data = $request->validated();
        $chargeItems = $data['charge_items'] ?? [];
        unset($data['charge_items']);

        $visit = $client->visits()->create([
            ...$data,
            'attendance_status' => AttendanceStatus::WalkIn->value,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $client->forceFill(['last_visit_at' => $visit->visit_date?->toDateString().' '.($visit->start_time ?? '00:00:00')])->save();
        $this->treatmentCharges->syncItems($client, TreatmentCharge::SOURCE_VISIT, $visit->id, $chargeItems);

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Visit created successfully.', 201);
    }

    public function update(UpdateVisitRequest $request, Visit $visit)
    {
        $data = $request->validated();
        $chargeItems = $data['charge_items'] ?? [];
        unset($data['charge_items']);

        $visit->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        if (array_key_exists('charge_items', $request->validated())) {
            $this->treatmentCharges->syncItems($visit->client, TreatmentCharge::SOURCE_VISIT, $visit->id, $chargeItems);
        }

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Visit updated successfully.');
    }

    public function destroy(Visit $visit)
    {
        $this->treatmentCharges->deleteForSource(TreatmentCharge::SOURCE_VISIT, $visit->id);
        $visit->delete();

        return $this->success(null, 'Visit deleted successfully.');
    }

    public function checkIn(CheckInAppointmentRequest $request, Appointment $appointment)
    {
        $visit = DB::transaction(function () use ($request, $appointment) {
            $this->assertAppointmentCanBeClosed($appointment);

            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'summary' => $request->validated('summary') ?? $appointment->planned_summary,
                'notes' => $request->validated('notes') ?? $appointment->planned_notes,
                'attendance_status' => AttendanceStatus::Attended->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->update([
                'status' => AppointmentStatus::Completed->value,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->client?->forceFill([
                'last_visit_at' => $appointment->date->format('Y-m-d').' '.$appointment->start_time,
            ])->save();

            // The appointment may already carry a charge (an AI-confirmed plan's
            // computed cost, or one added while editing the appointment before
            // check-in) -- re-point it to the new visit instead of leaving it
            // orphaned on the now-completed appointment or losing it entirely.
            $this->treatmentCharges->retarget(TreatmentCharge::SOURCE_AI_PLAN, $appointment->id, TreatmentCharge::SOURCE_VISIT, $visit->id);
            $this->treatmentCharges->retarget(TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, TreatmentCharge::SOURCE_VISIT, $visit->id);

            // If the odontogram was edited as part of checking in (the "Attended"
            // flow lets the doctor adjust it before it becomes a visit), the
            // retargeted charges above still hold the pre-edit line items -- replace
            // them with whatever was actually just computed for this visit.
            if ($request->has('charge_items')) {
                $this->treatmentCharges->syncItems(
                    $appointment->client,
                    TreatmentCharge::SOURCE_VISIT,
                    $visit->id,
                    $request->validated('charge_items') ?? [],
                );
            }

            return $visit;
        });

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Appointment checked in successfully.');
    }

    public function noShow(NoShowAppointmentRequest $request, Appointment $appointment)
    {
        $visit = DB::transaction(function () use ($request, $appointment) {
            $this->assertAppointmentCanBeClosed($appointment);

            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'notes' => $request->validated('notes'),
                'attendance_status' => AttendanceStatus::NoShow->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $appointment->update([
                'status' => AppointmentStatus::NoShow->value,
                'updated_by' => $request->user()->id,
            ]);

            // Nothing was actually performed, so any charge tied to this
            // appointment (an AI-confirmed plan's cost, or one added manually)
            // shouldn't count toward the client's total anymore.
            $this->treatmentCharges->deleteAllForAppointment($appointment->id);

            return $visit;
        });

        return $this->success(VisitResource::make($visit->load(['doctor', 'appointment'])), 'Appointment marked as no show successfully.');
    }

    protected function assertAppointmentCanBeClosed(Appointment $appointment): void
    {
        if ($appointment->status !== AppointmentStatus::Scheduled) {
            throw ValidationException::withMessages([
                'appointment' => ['Only scheduled appointments can be checked in or marked as no show.'],
            ]);
        }

        if ($appointment->visit()->exists()) {
            throw ValidationException::withMessages([
                'appointment' => ['This appointment already has a visit.'],
            ]);
        }
    }
}
