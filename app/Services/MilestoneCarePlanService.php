<?php

namespace App\Services;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Carbon\Carbon;

/**
 * Shared by every specialty whose one real clinical workflow so far is "a
 * fixed list of visits, each a number of days after some anchor date" --
 * Medivaria's chronic-disease follow-up, Orthovaria's injury rehab timeline,
 * and Estevaria's treatment-session package all fit this shape. Gynevaria's
 * PrenatalCarePlanService is kept separate and untouched: its milestones are
 * anchored to a biological event (LMP) with real domain math (Naegele's
 * rule) attached, not just a generic day-offset list, and it already shipped
 * and works -- not worth the risk of folding it into this later, more
 * generic service.
 *
 * @phpstan-type Milestone array{title: string, day_offset: int, catalog_code: string, clinical_data?: ?array}
 */
class MilestoneCarePlanService
{
    public function __construct(protected CarePlanService $carePlans) {}

    /**
     * @param  array<int, array{title: string, day_offset: int, catalog_code: string, clinical_data?: ?array}>  $milestones
     */
    public function confirmPlan(
        Client $client,
        User $doctor,
        Specialty $specialty,
        string $planTitle,
        string $anchorDate,
        string $preferredStartTime,
        array $milestones,
        int $userId,
        ?string $summary = null,
    ): CarePlan {
        $anchor = Carbon::parse($anchorDate);

        $catalogByCode = TreatmentCatalog::query()
            ->where('company_id', $client->company_id)
            ->where('specialty_id', $specialty->id)
            ->get()
            ->keyBy('code');

        $sessions = collect($milestones)->map(function (array $milestone) use ($anchor, $preferredStartTime, $catalogByCode) {
            $catalogItem = $catalogByCode->get($milestone['catalog_code']);

            return [
                'date' => $anchor->copy()->addDays($milestone['day_offset'])->toDateString(),
                'start_time' => $preferredStartTime,
                'duration_minutes' => 30,
                'title' => $milestone['title'],
                'clinical_data' => $milestone['clinical_data'] ?? null,
                'charge_items' => $catalogItem ? [[
                    'description' => $catalogItem->name_en ?? $catalogItem->name_ar ?? $catalogItem->name_tr,
                    'amount' => (float) $catalogItem->default_price,
                    'treatment_catalog_id' => $catalogItem->id,
                ]] : [],
            ];
        })->all();

        return $this->carePlans->confirmPlan($client, $doctor, $specialty, $planTitle, $sessions, $userId, $summary);
    }
}
