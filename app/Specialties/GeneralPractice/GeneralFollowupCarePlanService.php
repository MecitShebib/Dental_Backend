<?php

namespace App\Specialties\GeneralPractice;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Genervaria's general follow-up: an examination, a follow-up visit two
 * weeks later, and a periodic check-up at three months. v1 prototype
 * cadence; review by a clinician before relying on it.
 */
class GeneralFollowupCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'General Examination', 'catalog_code' => 'gp_examination'],
            ['day_offset' => 14, 'title' => 'Follow-up Visit', 'catalog_code' => 'gp_followup'],
            ['day_offset' => 90, 'title' => 'Periodic Check-up', 'catalog_code' => 'gp_checkup'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $complaint, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::GENERAL_PRACTICE)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['complaint' => $complaint],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'General Follow-up Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Chief complaint: {$complaint}",
        );
    }
}
