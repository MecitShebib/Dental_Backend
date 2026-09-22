<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Models\Appointment;
use App\Models\TreatmentCharge;
use App\Models\Visit;

/**
 * Turns a just-created, still-"scheduled" Appointment into an already-
 * attended Visit immediately -- used only when an AI-confirmed treatment
 * plan (any specialty) schedules a session for *today*: the doctor is
 * confirming the plan with the patient physically present, so today's
 * appointment isn't a future booking waiting to be checked in later, it
 * already happened. See AiTreatmentPlanService::confirm() (dental) and
 * SpecialtyAiTreatmentPlanService::confirm() (the other 4).
 *
 * Deliberately mirrors ClientVisitController::checkIn() (the doctor-
 * initiated "Attended" button) rather than sharing code with it -- that
 * endpoint is already tested/in production and takes a request-specific
 * summary/notes override this call site has no equivalent for, so it
 * always falls back to the appointment's own planned_summary/planned_notes,
 * exactly like checkIn() does when the doctor doesn't type an override.
 */
class AppointmentAutoAttendanceService
{
    public function __construct(protected TreatmentChargeService $treatmentCharges) {}

    public function attendNow(Appointment $appointment, int $userId): Visit
    {
        $visit = $appointment->visit()->create([
            'client_id' => $appointment->client_id,
            'doctor_id' => $appointment->doctor_id,
            'visit_date' => $appointment->date,
            'start_time' => $appointment->start_time,
            'duration_minutes' => $appointment->duration_minutes,
            'summary' => $appointment->planned_summary,
            'notes' => $appointment->planned_notes,
            'attendance_status' => AttendanceStatus::Attended->value,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $appointment->update([
            'status' => AppointmentStatus::Completed->value,
            'updated_by' => $userId,
        ]);

        $appointment->client?->forceFill([
            'last_visit_at' => $appointment->date->format('Y-m-d').' '.$appointment->start_time,
        ])->save();

        // Same two-source retarget checkIn() does -- the charge synced onto
        // this appointment moments ago (SOURCE_AI_PLAN) needs to follow it
        // onto the visit, not be left orphaned on a now-completed
        // appointment or double-counted alongside a later edit.
        $this->treatmentCharges->retarget(TreatmentCharge::SOURCE_AI_PLAN, $appointment->id, TreatmentCharge::SOURCE_VISIT, $visit->id);
        $this->treatmentCharges->retarget(TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, TreatmentCharge::SOURCE_VISIT, $visit->id);

        return $visit;
    }
}
