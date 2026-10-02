<?php

namespace App\Specialties\Physiotherapy;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Physiovaria's physiotherapy session series: an initial evaluation, eight
 * sessions over four weeks (roughly 2-3 per week) with a re-evaluation at
 * two weeks, and a final evaluation. v1 prototype cadence -- a real
 * protocol varies by diagnosis; review by a physiotherapist before this is
 * treated as more than a starting template.
 */
class PhysioSessionCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'Initial Evaluation', 'catalog_code' => 'phy_initial_evaluation'],
            ['day_offset' => 2, 'title' => 'Session 1', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 4, 'title' => 'Session 2', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 7, 'title' => 'Session 3', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 9, 'title' => 'Session 4', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 11, 'title' => 'Session 5', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 14, 'title' => 'Re-evaluation', 'catalog_code' => 'phy_reevaluation'],
            ['day_offset' => 16, 'title' => 'Session 6', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 18, 'title' => 'Session 7', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 21, 'title' => 'Session 8', 'catalog_code' => 'phy_therapy_session'],
            ['day_offset' => 28, 'title' => 'Final Evaluation', 'catalog_code' => 'phy_final_evaluation'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $diagnosis, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::PHYSIOTHERAPY)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['diagnosis' => $diagnosis],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Physiotherapy Session Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Diagnosis: {$diagnosis}",
        );
    }
}
