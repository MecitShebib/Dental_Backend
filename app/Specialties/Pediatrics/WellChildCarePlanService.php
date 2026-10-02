<?php

namespace App\Specialties\Pediatrics;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Pediavaria's well-child follow-up timeline, anchored to the child's
 * 1st-month visit date (not the birth date -- anchoring to birth would book
 * past-dated appointments for any older child). v1 prototype cadence
 * loosely following the Turkish childhood vaccination calendar; needs
 * review by a pediatrician before being treated as clinical guidance.
 */
class WellChildCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => '1st Month Well-Child Visit', 'catalog_code' => 'ped_well_child_visit'],
            ['day_offset' => 30, 'title' => '2nd Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 90, 'title' => '4th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 150, 'title' => '6th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 240, 'title' => '9th Month Well-Child Visit', 'catalog_code' => 'ped_well_child_visit'],
            ['day_offset' => 330, 'title' => '12th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 510, 'title' => '18th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 690, 'title' => '24th Month Growth Assessment', 'catalog_code' => 'ped_growth_assessment'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $notes, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::PEDIATRICS)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['notes' => $notes],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Well-Child Follow-up Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Notes: {$notes}",
        );
    }
}
