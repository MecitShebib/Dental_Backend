<?php

namespace App\Specialties\GeneralSurgery;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Surgivaria's perioperative timeline, anchored to the pre-operative
 * evaluation date (so no milestone ever lands before the anchor): pre-op
 * evaluation, surgery a week later, wound care / suture removal a week
 * after surgery, and a post-op control a month after surgery. v1 prototype
 * cadence -- review by a surgeon before relying on it.
 */
class PerioperativeCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'Pre-operative Evaluation', 'catalog_code' => 'gs_preop_evaluation'],
            ['day_offset' => 7, 'title' => 'Surgery', 'catalog_code' => 'gs_surgery'],
            ['day_offset' => 14, 'title' => 'Wound Care & Suture Removal', 'catalog_code' => 'gs_wound_care'],
            ['day_offset' => 37, 'title' => 'Post-operative Control', 'catalog_code' => 'gs_postop_control'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $operation, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::GENERAL_SURGERY)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['operation' => $operation],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Perioperative Care Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Planned operation: {$operation}",
        );
    }
}
