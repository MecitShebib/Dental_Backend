<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TreatmentCharge;
use App\Models\TreatmentChargeInventoryConsumption;

/**
 * Keeps a client's treatment_charges ledger in sync with whatever visit,
 * appointment, or AI-confirmed plan session drove it: one charge row per
 * source record, created/updated/deleted alongside that record so
 * ClientFinancialSummaryService's totals never drift from what's actually on
 * the client's timeline.
 */
class TreatmentChargeService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Replaces every charge row for a source with exactly the line items
     * given -- the caller (visit/appointment/AI-plan-session save) always
     * sends its full current set of procedures (plus any discount, itself
     * just a negative-amount item), so a delete-then-insert keeps this
     * source's rows an exact mirror of that set without needing to diff
     * individual items.
     *
     * @param  array<int, array{description?: ?string, amount: float, treatment_catalog_id?: ?int}>  $items
     */
    public function syncItems(Client $client, string $sourceType, int $sourceId, array $items): void
    {
        $this->deleteForSource($sourceType, $sourceId);

        if (! empty($items)) {
            $client->treatmentCharges()->createMany(
                collect($items)->map(fn (array $item) => [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'amount' => (float) $item['amount'],
                    'description' => $item['description'] ?? null,
                    'treatment_catalog_id' => $item['treatment_catalog_id'] ?? null,
                ])->all()
            );
        }

        // Only line items picked from the priced catalog carry a
        // treatment_catalog_id (see ChargeItemsEditor on the frontend) --
        // free-text/manual amounts never trigger inventory consumption.
        $catalogIdCounts = collect($items)
            ->filter(fn (array $item) => ! empty($item['treatment_catalog_id']))
            ->countBy('treatment_catalog_id')
            ->all();

        $this->inventory->syncConsumptionForSource($client->company_id, $sourceType, $sourceId, $catalogIdCounts);
    }

    public function deleteForSource(string $sourceType, int $sourceId): void
    {
        TreatmentCharge::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }

    /**
     * Re-points an existing charge to a new source without losing its
     * created_by/timestamps -- used when an appointment (source "ai_plan" or
     * "appointment") is checked in and becomes a visit, so the same charge
     * keeps tracking the same real-world treatment instead of being
     * duplicated or orphaned. Also re-points any auto-consumption tracking
     * rows for that source, so a later re-sync under the new source key
     * diffs against the right baseline instead of re-consuming from scratch.
     */
    public function retarget(string $fromType, int $fromId, string $toType, int $toId): void
    {
        TreatmentCharge::query()
            ->where('source_type', $fromType)
            ->where('source_id', $fromId)
            ->update(['source_type' => $toType, 'source_id' => $toId]);

        TreatmentChargeInventoryConsumption::query()
            ->where('source_type', $fromType)
            ->where('source_id', $fromId)
            ->update(['source_type' => $toType, 'source_id' => $toId]);
    }

    /**
     * An appointment's charge may have been created under either "ai_plan"
     * (auto-computed at plan confirm) or "appointment" (added/edited later) --
     * callers acting on the appointment itself (delete, no-show) don't need to
     * know which, so this clears both possibilities for that appointment id.
     * Also reverses any auto-consumed inventory for either source, since
     * there is no charge sync afterwards to diff against.
     */
    public function deleteAllForAppointment(int $appointmentId, ?int $companyId = null): void
    {
        $this->deleteForSource(TreatmentCharge::SOURCE_AI_PLAN, $appointmentId);
        $this->deleteForSource(TreatmentCharge::SOURCE_APPOINTMENT, $appointmentId);

        $this->reverseConsumptionForSource($companyId, TreatmentCharge::SOURCE_AI_PLAN, $appointmentId);
        $this->reverseConsumptionForSource($companyId, TreatmentCharge::SOURCE_APPOINTMENT, $appointmentId);
    }

    /**
     * Reverses any inventory auto-consumed for a source that's being deleted
     * outright with no follow-up charge sync to diff against -- a no-op if
     * that source never triggered any auto-consumption in the first place.
     */
    public function reverseConsumptionForSource(?int $companyId, string $sourceType, int $sourceId): void
    {
        if ($companyId === null) {
            return;
        }

        $this->inventory->syncConsumptionForSource($companyId, $sourceType, $sourceId, []);
    }

    /**
     * Sums a doctor's realized treatment revenue for a calendar month, the
     * base figure payroll commission is computed from. Only source_type=visit
     * charges count: check-in retargets an appointment's (or AI plan's)
     * charges onto the visit it produces, so by the time a visit exists it's
     * the sole, de-duplicated owner of that treatment's charges. A charge
     * still sitting on a not-yet-checked-in appointment isn't realized income
     * yet and is deliberately excluded.
     */
    public function sumRealizedRevenueForDoctorInMonth(int $doctorId, int $year, int $month): float
    {
        return (float) TreatmentCharge::query()
            ->where('source_type', TreatmentCharge::SOURCE_VISIT)
            ->whereIn('source_id', function ($query) use ($doctorId, $year, $month) {
                $query->select('id')
                    ->from('visits')
                    ->whereNull('deleted_at')
                    ->where('doctor_id', $doctorId)
                    ->whereYear('visit_date', $year)
                    ->whereMonth('visit_date', $month);
            })
            ->sum('amount');
    }
}
