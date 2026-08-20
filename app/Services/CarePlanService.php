<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\TreatmentCharge;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Specialty-agnostic generalization of AiTreatmentPlanService::confirm():
 * turn a set of planned sessions into real Appointments plus itemized
 * pending charges, the same "plan -> scheduled, billable visits" shape every
 * specialty needs. Dental keeps using its own existing, already-tested flow
 * (AiTreatmentPlanService) unchanged -- this exists for the *next* specialty
 * module to build on, not to replace what already works.
 *
 * Each session's `clinical_data` is an opaque array as far as this service
 * is concerned; interpreting it (rendering it, validating its shape) is each
 * specialty module's own job, the same way the odontogram-v2 JSON inside
 * TreatmentRecord.notes is opaque to everything except the dental frontend.
 *
 * @phpstan-type SessionInput array{date: string, start_time: string, duration_minutes: int, title: string, notes?: ?string, clinical_data?: ?array, charge_items?: array}
 */
class CarePlanService
{
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    /**
     * @param  array<int, array{date: string, start_time: string, duration_minutes: int, title: string, notes?: ?string, clinical_data?: ?array, charge_items?: array}>  $sessions
     */
    public function confirmPlan(
        Client $client,
        User $doctor,
        Specialty $specialty,
        string $title,
        array $sessions,
        int $userId,
        ?string $summary = null,
    ): CarePlan {
        if (empty($sessions)) {
            throw ValidationException::withMessages([
                'sessions' => ['A care plan needs at least one session.'],
            ]);
        }

        $this->assertNoIntraBatchOverlap($sessions);

        foreach ($sessions as $session) {
            $this->conflicts->assertWithinSchedule($doctor, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
            $this->conflicts->assertNoConflict($doctor->id, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
        }

        return DB::transaction(function () use ($client, $doctor, $specialty, $title, $sessions, $userId, $summary) {
            $this->enrollment->ensureEnrolled($client, $doctor);

            $plan = CarePlan::create([
                'specialty_id' => $specialty->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'created_by' => $userId,
                'title' => $title,
                'summary' => $summary,
                'status' => CarePlan::STATUS_CONFIRMED,
            ]);

            foreach ($sessions as $index => $session) {
                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'doctor_id' => $doctor->id,
                    'type' => AppointmentType::Booked->value,
                    'status' => AppointmentStatus::Scheduled->value,
                    'date' => $session['date'],
                    'start_time' => $session['start_time'],
                    'duration_minutes' => (int) $session['duration_minutes'],
                    'end_time' => $this->conflicts->calculateEndTime($session['start_time'], (int) $session['duration_minutes']),
                    'planned_notes' => $session['notes'] ?? null,
                    'planned_summary' => $session['title'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $plan->sessions()->create([
                    'appointment_id' => $appointment->id,
                    'session_index' => $index,
                    'title' => $session['title'],
                    'notes' => $session['notes'] ?? null,
                    'clinical_data' => $session['clinical_data'] ?? null,
                ]);

                // Same source type dental's AI plan uses (TreatmentCharge::
                // SOURCE_AI_PLAN, keyed by appointment id) -- the existing,
                // specialty-agnostic check-in flow (ClientVisitController)
                // already retargets this to SOURCE_VISIT automatically, so
                // billing/check-in "just works" for any specialty without
                // touching that controller.
                $this->treatmentCharges->syncItems(
                    $client,
                    TreatmentCharge::SOURCE_AI_PLAN,
                    $appointment->id,
                    $session['charge_items'] ?? [],
                );
            }

            return $plan->fresh('sessions.appointment');
        });
    }

    /**
     * @param  array<int, array{date: string, start_time: string, duration_minutes: int}>  $sessions
     */
    protected function assertNoIntraBatchOverlap(array $sessions): void
    {
        foreach ($sessions as $i => $sessionA) {
            $startA = Carbon::parse($sessionA['date'].' '.$sessionA['start_time']);
            $endA = $startA->copy()->addMinutes((int) $sessionA['duration_minutes']);

            foreach ($sessions as $j => $sessionB) {
                if ($j <= $i) {
                    continue;
                }

                $startB = Carbon::parse($sessionB['date'].' '.$sessionB['start_time']);
                $endB = $startB->copy()->addMinutes((int) $sessionB['duration_minutes']);

                if ($startA->lt($endB) && $endA->gt($startB)) {
                    throw ValidationException::withMessages([
                        'sessions' => ['Two sessions in this plan overlap with each other.'],
                    ]);
                }
            }
        }
    }
}
