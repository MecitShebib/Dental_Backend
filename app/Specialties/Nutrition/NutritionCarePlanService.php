<?php

namespace App\Specialties\Nutrition;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;
use Illuminate\Validation\ValidationException;

/**
 * Dietavaria's one real clinical workflow so far: "follow-up program
 * tracking" -- a one-off consultation followed by a chosen number of
 * same-type sessions, evenly spaced. Same shape as Estevaria's
 * CosmeticCarePlanService: the session count and spacing are picked by the
 * doctor per program (a 12-week weekly weigh-in program looks nothing like
 * a monthly body-composition check-in), so the milestone list is built
 * dynamically before handing off to the shared MilestoneCarePlanService.
 * Not a substitute for a real per-program clinical protocol; review by a
 * clinical stakeholder before this becomes more than a prototype.
 */
class NutritionCarePlanService
{
    public const TREATMENT_LABELS = [
        'followup_session' => 'Follow-up Session',
        'body_composition_analysis' => 'Body Composition Analysis',
    ];

    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    public function confirmPlan(
        Client $client,
        User $doctor,
        string $treatmentCode,
        int $sessionCount,
        int $intervalDays,
        string $startDate,
        string $preferredStartTime,
        int $userId,
    ): CarePlan {
        if (! array_key_exists($treatmentCode, self::TREATMENT_LABELS)) {
            throw ValidationException::withMessages([
                'treatment_code' => ['Unknown session type.'],
            ]);
        }

        $specialty = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $label = self::TREATMENT_LABELS[$treatmentCode];

        // The "cycle" record for the periodic follow-up loop (see
        // docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md
        // section 6): whatever measurement was most recent at confirmation
        // time becomes this program's baseline. No new table -- it rides on
        // the same clinical_data JSON convention program_session_type/
        // session_count/session_number already use. AiConversationService
        // reads this back to compare a later measurement against it.
        $baselineMetric = $client->nutritionBodyMetrics()->first();

        $milestones = [[
            'day_offset' => 0,
            'title' => 'Initial Nutrition Consultation',
            'catalog_code' => 'nutrition_consultation',
            'clinical_data' => [
                'program_session_type' => $label,
                'session_count' => $sessionCount,
                'baseline_metric_id' => $baselineMetric?->id,
                'baseline_recorded_at' => $baselineMetric?->recorded_at?->toDateString(),
            ],
        ]];

        for ($session = 1; $session <= $sessionCount; $session++) {
            $milestones[] = [
                'day_offset' => $intervalDays * $session,
                'title' => "{$label} ({$session}/{$sessionCount})",
                'catalog_code' => $treatmentCode,
                'clinical_data' => ['session_number' => $session, 'session_count' => $sessionCount],
            ];
        }

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            "{$label} Program",
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "{$sessionCount}-session {$label} program, every {$intervalDays} days",
        );
    }
}
