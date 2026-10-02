<?php

namespace App\Observers;

use App\Enums\AppointmentType;
use App\Mail\NewAppointmentForDoctorMail;
use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the doctor whenever an appointment is created for them. Done as a
 * model event because appointments are created from many places
 * (AppointmentController + the per-specialty ones, PublicBookingService,
 * AiTreatmentPlanService, SpecialtyAiTreatmentPlanService, CarePlanService).
 *
 * After-commit so a rolled-back booking never emails; sent synchronously
 * (this host has no queue worker -- see VisitObserver); a mail failure is
 * logged, never allowed to break the booking itself. Skipped when the
 * doctor created the appointment themselves.
 */
class AppointmentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Appointment $appointment): void
    {
        // "unavailable" rows are blocked-off time, not a patient booking.
        if ($appointment->type === AppointmentType::Unavailable || ! $appointment->client_id) {
            return;
        }

        $appointment->loadMissing(['doctor', 'client', 'company']);
        $doctor = $appointment->doctor;

        if (! $doctor || blank($doctor->email)) {
            return;
        }
        if ($appointment->created_by !== null && (int) $appointment->created_by === (int) $doctor->id) {
            return;
        }

        try {
            Mail::to($doctor->email)->send(new NewAppointmentForDoctorMail($appointment));
        } catch (Throwable $e) {
            Log::warning('New-appointment email to doctor failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
