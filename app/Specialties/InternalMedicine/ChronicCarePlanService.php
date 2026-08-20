<?php

namespace App\Specialties\InternalMedicine;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Medivaria's one real clinical workflow so far: a generic chronic-disease
 * follow-up schedule (initial assessment, then a 2-week check, a lab review,
 * and monthly follow-ups out to 3 months) via the shared
 * MilestoneCarePlanService. Deliberately condition-agnostic -- v1 does not
 * branch by diagnosis (diabetes vs. hypertension vs. other), it's the same
 * cadence for any chronic condition. Not a substitute for a real internal
 * medicine follow-up protocol; review by a clinical stakeholder before this
 * becomes more than a prototype (see PrenatalCarePlanService for the same
 * caveat applied to Gynevaria).
 */
class ChronicCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'Initial Assessment', 'catalog_code' => 'chronic_initial_assessment'],
            ['day_offset' => 14, 'title' => '2-Week Follow-up', 'catalog_code' => 'chronic_followup_visit'],
            ['day_offset' => 30, 'title' => 'Lab Panel Review', 'catalog_code' => 'lab_panel'],
            ['day_offset' => 60, 'title' => '1-Month Follow-up', 'catalog_code' => 'chronic_followup_visit'],
            ['day_offset' => 90, 'title' => '3-Month Follow-up', 'catalog_code' => 'chronic_followup_visit'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $condition, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['condition' => $condition],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Chronic Disease Follow-up Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Condition: {$condition}",
        );
    }
}
