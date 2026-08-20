<?php

namespace App\Specialties\Orthopedics;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Orthovaria's one real clinical workflow so far: a generic 6-week injury
 * rehab timeline (initial assessment, weekly physical therapy, a mid-course
 * follow-up X-ray, a final assessment) via the shared
 * MilestoneCarePlanService. Deliberately injury-agnostic -- v1 does not
 * branch by injury/procedure type, it's the same cadence for any of them.
 * Not a substitute for a real physiotherapy protocol; review by a clinical
 * stakeholder before this becomes more than a prototype (see
 * PrenatalCarePlanService for the same caveat applied to Gynevaria).
 */
class RehabCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'Initial Assessment', 'catalog_code' => 'ortho_assessment'],
            ['day_offset' => 7, 'title' => 'Physical Therapy - Week 1', 'catalog_code' => 'physical_therapy_session'],
            ['day_offset' => 14, 'title' => 'Physical Therapy - Week 2', 'catalog_code' => 'physical_therapy_session'],
            ['day_offset' => 21, 'title' => 'Follow-up X-Ray', 'catalog_code' => 'followup_xray'],
            ['day_offset' => 28, 'title' => 'Physical Therapy - Week 4', 'catalog_code' => 'physical_therapy_session'],
            ['day_offset' => 42, 'title' => 'Final Assessment', 'catalog_code' => 'final_assessment'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $injury, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::ORTHOPEDICS)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['injury' => $injury],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Injury Rehab Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Injury/procedure: {$injury}",
        );
    }
}
