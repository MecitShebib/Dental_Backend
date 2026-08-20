<?php

namespace App\Specialties\Gynecology;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Services\CarePlanService;
use Carbon\Carbon;

/**
 * Gynevaria's one real clinical workflow so far: given a last menstrual
 * period (LMP) date, generate a standard trimester-milestone visit schedule
 * and confirm it via the generic CarePlanService -- the same "plan becomes
 * scheduled, billable Appointments" engine dental's AI plans use.
 *
 * The milestone schedule below (checkup + ultrasound around the classic
 * dating/anomaly-scan windows, one checkup per trimester) is a deliberately
 * simple v1 matching the product spec's "trimestere göre otomatik randevu
 * takvimi" (trimester-based automatic schedule) -- it is not a substitute
 * for a real obstetric care protocol (e.g. ACOG's actual visit cadence is
 * far more frequent, especially in the third trimester) and should be
 * reviewed by a clinical stakeholder before this becomes more than a
 * prototype.
 */
class PrenatalCarePlanService
{
    public function __construct(protected CarePlanService $carePlans) {}

    protected function milestones(): array
    {
        return [
            ['weeks' => 8, 'title' => 'First Trimester Checkup', 'catalog_code' => 'prenatal_checkup', 'trimester' => 1],
            ['weeks' => 12, 'title' => 'Dating Ultrasound', 'catalog_code' => 'ultrasound', 'trimester' => 1],
            ['weeks' => 20, 'title' => 'Second Trimester Checkup', 'catalog_code' => 'prenatal_checkup', 'trimester' => 2],
            ['weeks' => 22, 'title' => 'Anomaly Ultrasound', 'catalog_code' => 'ultrasound', 'trimester' => 2],
            ['weeks' => 32, 'title' => 'Third Trimester Checkup', 'catalog_code' => 'prenatal_checkup', 'trimester' => 3],
        ];
    }

    public function estimatedDueDate(string $lastMenstrualPeriod): string
    {
        // Naegele's rule: EDD = LMP + 280 days. Standard obstetric estimate,
        // not itself a clinical judgment.
        return Carbon::parse($lastMenstrualPeriod)->addDays(280)->toDateString();
    }

    public function confirmPlan(Client $client, User $doctor, string $lastMenstrualPeriod, string $preferredStartTime, int $userId): CarePlan
    {
        $lmp = Carbon::parse($lastMenstrualPeriod);
        $specialty = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $edd = $this->estimatedDueDate($lastMenstrualPeriod);

        $catalogByCode = TreatmentCatalog::query()
            ->where('company_id', $client->company_id)
            ->where('specialty_id', $specialty->id)
            ->get()
            ->keyBy('code');

        $sessions = collect($this->milestones())->map(function (array $milestone) use ($lmp, $preferredStartTime, $catalogByCode, $edd) {
            $catalogItem = $catalogByCode->get($milestone['catalog_code']);

            return [
                'date' => $lmp->copy()->addWeeks($milestone['weeks'])->toDateString(),
                'start_time' => $preferredStartTime,
                'duration_minutes' => 30,
                'title' => $milestone['title'],
                'clinical_data' => [
                    'trimester' => $milestone['trimester'],
                    'gestational_week' => $milestone['weeks'],
                    'lmp' => $lmp->toDateString(),
                    'estimated_due_date' => $edd,
                ],
                'charge_items' => $catalogItem ? [[
                    'description' => $catalogItem->name_en ?? $catalogItem->name_ar ?? $catalogItem->name_tr,
                    'amount' => (float) $catalogItem->default_price,
                    'treatment_catalog_id' => $catalogItem->id,
                ]] : [],
            ];
        })->all();

        return $this->carePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Prenatal Care Plan',
            $sessions,
            $userId,
            "Estimated due date: {$edd} (LMP: {$lmp->toDateString()})",
        );
    }
}
