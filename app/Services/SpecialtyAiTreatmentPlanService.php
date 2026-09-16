<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\TreatmentCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The non-dental equivalent of AiTreatmentPlanService -- shared by
 * Gynevaria/Medivaria/Orthovaria/Estevaria's AI treatment plan assistants.
 * Dental's own AiTreatmentPlanService is odontogram-based (a `teeth` array
 * per session, a rendered odontogram summary); this one is procedure-code
 * based (a small `procedures` array constrained to that specialty's own
 * 4-item catalog, via SpecialtyAiProfiles) since there's no equivalent
 * structured clinical widget for these specialties. Kept as a fully
 * separate class rather than parametrizing AiTreatmentPlanService itself --
 * same "don't touch what already works" reasoning as
 * PrenatalCarePlanService being kept separate from MilestoneCarePlanService.
 */
class SpecialtyAiTreatmentPlanService
{
    public function __construct(
        protected OpenAiClient $openAi,
        protected DoctorAvailabilityService $availability,
        protected AppointmentConflictService $conflicts,
        protected AiTokenUsageService $aiTokenUsage,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function buildJsonSchema(Specialty $specialty): array
    {
        $procedureCodes = SpecialtyAiProfiles::procedureCodes($specialty->key);

        $procedureSchema = [
            'type' => 'object',
            'properties' => [
                'procedure_code' => ['type' => 'string', 'enum' => $procedureCodes],
                'notes' => ['type' => ['string', 'null']],
            ],
            'required' => ['procedure_code', 'notes'],
            'additionalProperties' => false,
        ];

        $sessionSchema = [
            'type' => 'object',
            'properties' => [
                'day_offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 60],
                'duration_minutes' => ['type' => 'integer', 'enum' => [30, 60, 90]],
                'session_description' => ['type' => 'string'],
                'procedures' => [
                    'type' => 'array',
                    'items' => $procedureSchema,
                    'minItems' => 0,
                    'maxItems' => 6,
                ],
            ],
            'required' => ['day_offset', 'duration_minutes', 'session_description', 'procedures'],
            'additionalProperties' => false,
        ];

        $properties = [
            'diagnosis_summary' => ['type' => 'string'],
            'sessions' => [
                'type' => 'array',
                'items' => $sessionSchema,
                'minItems' => 1,
                'maxItems' => 8,
            ],
        ];
        $required = ['diagnosis_summary', 'sessions'];

        // Nutrition-only, per docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md's
        // follow-up scope: the AI produces a written diet plan and exercise
        // plan alongside the scheduled sessions, not just appointments.
        // additionalProperties=false means every OTHER specialty must NOT
        // declare these two -- OpenAI's strict structured-output mode
        // rejects a schema/response mismatch, so this stays conditional.
        if ($specialty->key === Specialty::NUTRITION) {
            $properties['diet_plan'] = ['type' => 'string'];
            $properties['exercise_plan'] = ['type' => 'string'];
            $required[] = 'diet_plan';
            $required[] = 'exercise_plan';
        }

        return [
            'name' => 'specialty_treatment_plan',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
        ];
    }

    public function resolveSessionSlot(mixed $doctor, Carbon $fromDate, int $durationMinutes, int $searchDays = 14): array
    {
        $cursor = $fromDate->copy();
        $now = now();

        for ($attempt = 0; $attempt < $searchDays; $attempt++) {
            try {
                $times = $this->availability->availableStartTimes($doctor, $cursor->toDateString(), $durationMinutes);
                $startTimes = $times['start_times'];

                // The schedule grid doesn't know what time it is "now" -- on
                // the very first candidate day, a slot earlier than the
                // current time is still technically "free" but can't
                // actually be booked, so don't let the AI pick it.
                if ($cursor->isSameDay($now)) {
                    $startTimes = array_values(array_filter(
                        $startTimes,
                        fn (string $time) => Carbon::parse($cursor->toDateString().' '.$time)->greaterThan($now),
                    ));
                }

                if (! empty($startTimes)) {
                    return [
                        'date' => $cursor->toDateString(),
                        'start_time' => $startTimes[0],
                    ];
                }
            } catch (ValidationException) {
                // Doctor has no schedule for this weekday -- try the next day.
            }

            $cursor->addDay();
        }

        throw ValidationException::withMessages([
            'sessions' => ["No available slot found for the doctor within {$searchDays} days starting from {$fromDate->toDateString()}."],
        ]);
    }

    public function generatePlanFromMessages(Specialty $specialty, mixed $doctor, mixed $actingUser, Client $client, array $messages): array
    {
        $response = $this->openAi->chatCompletionJson($messages, $this->buildJsonSchema($specialty));

        $this->aiTokenUsage->recordUsage(
            $actingUser->company,
            $actingUser,
            $client,
            'ai_treatment_plan_generate',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $response['usage']['prompt_tokens'],
            (int) $response['usage']['completion_tokens'],
        );

        $result = $response['content'];
        $sessions = [];
        $cursor = Carbon::now()->startOfDay();

        foreach (array_slice($result['sessions'], 0, 8) as $session) {
            $cursor = $cursor->copy()->addDays((int) $session['day_offset']);
            $slot = $this->resolveSessionSlot($doctor, $cursor, (int) $session['duration_minutes']);
            $cursor = Carbon::parse($slot['date']);

            $sessions[] = [
                'date' => $slot['date'],
                'start_time' => $slot['start_time'],
                'duration_minutes' => (int) $session['duration_minutes'],
                'session_description' => $session['session_description'],
                'procedures' => $session['procedures'],
            ];
        }

        return [
            'diagnosis_summary' => $result['diagnosis_summary'],
            'sessions' => $sessions,
            'diet_plan' => $result['diet_plan'] ?? null,
            'exercise_plan' => $result['exercise_plan'] ?? null,
            'usage' => $response['usage'],
        ];
    }

    /**
     * Unlike dental's confirm() (which derives planned_summary from the
     * proposed odontogram), this specialty's appointments carry only
     * planned_notes (free text) -- there's no structured viewer for
     * "procedures" the way there is for teeth, so planned_summary stays
     * null.
     *
     * @return array{appointments: \Illuminate\Support\Collection, care_plan: ?CarePlan}
     */
    public function confirm(
        Client $client,
        mixed $doctor,
        Specialty $specialty,
        array $sessions,
        int $userId,
        ?string $dietPlan = null,
        ?string $exercisePlan = null,
    ): array {
        $this->assertNoIntraBatchOverlap($sessions);

        foreach ($sessions as $session) {
            $this->conflicts->assertWithinSchedule($doctor, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
            $this->conflicts->assertNoConflict($doctor->id, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
        }

        return DB::transaction(function () use ($client, $doctor, $specialty, $sessions, $userId, $dietPlan, $exercisePlan) {
            $this->enrollment->ensureEnrolled($client, $doctor);

            $appointments = collect($sessions)->map(function (array $session) use ($client, $doctor, $userId) {
                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'doctor_id' => $doctor->id,
                    'type' => AppointmentType::Booked->value,
                    'status' => AppointmentStatus::Scheduled->value,
                    'date' => $session['date'],
                    'start_time' => $session['start_time'],
                    'duration_minutes' => (int) $session['duration_minutes'],
                    'end_time' => $this->conflicts->calculateEndTime($session['start_time'], (int) $session['duration_minutes']),
                    'planned_notes' => $session['session_description'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->treatmentCharges->syncItems(
                    $client,
                    TreatmentCharge::SOURCE_AI_PLAN,
                    $appointment->id,
                    $session['charge_items'] ?? [],
                );

                return $appointment->fresh();
            });

            // A CarePlan row only gets created when there's a diet/exercise
            // plan to hang it on (nutrition, in practice) -- the other 3
            // non-dental specialties keep behaving exactly as before
            // (appointments only, no CarePlan row), since confirm() is
            // shared across all 4 and dietPlan/exercisePlan are null there.
            $carePlan = null;
            if ($dietPlan || $exercisePlan) {
                $carePlan = CarePlan::create([
                    'company_id' => $client->company_id,
                    'specialty_id' => $specialty->id,
                    'client_id' => $client->id,
                    'doctor_id' => $doctor->id,
                    'created_by' => $userId,
                    'title' => 'AI Follow-up Plan '.now()->toDateString(),
                    'diet_plan' => $dietPlan,
                    'exercise_plan' => $exercisePlan,
                    'status' => CarePlan::STATUS_CONFIRMED,
                ]);
            }

            return ['appointments' => $appointments, 'care_plan' => $carePlan];
        });
    }

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
