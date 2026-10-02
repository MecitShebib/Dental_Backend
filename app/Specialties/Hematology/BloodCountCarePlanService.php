<?php

namespace App\Specialties\Hematology;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Hemavaria's blood-count follow-up: a consultation, CBC controls at weeks
 * 2/4/8/12, and a treatment evaluation. v1 prototype cadence (roughly what
 * iron-deficiency anemia follow-up looks like) -- other diagnoses need
 * different intervals; review by a hematologist before relying on it.
 */
class BloodCountCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => 'Hematology Consultation', 'catalog_code' => 'hem_consultation'],
            ['day_offset' => 14, 'title' => 'CBC Control - Week 2', 'catalog_code' => 'hem_cbc_control'],
            ['day_offset' => 28, 'title' => 'CBC Control - Week 4', 'catalog_code' => 'hem_cbc_control'],
            ['day_offset' => 56, 'title' => 'CBC Control - Week 8', 'catalog_code' => 'hem_cbc_control'],
            ['day_offset' => 84, 'title' => 'CBC Control - Week 12', 'catalog_code' => 'hem_cbc_control'],
            ['day_offset' => 90, 'title' => 'Treatment Evaluation', 'catalog_code' => 'hem_evaluation'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $diagnosis, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::HEMATOLOGY)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['diagnosis' => $diagnosis],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Blood Count Follow-up Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Diagnosis: {$diagnosis}",
        );
    }
}
