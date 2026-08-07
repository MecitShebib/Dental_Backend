<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TreatmentCharge;

/**
 * Keeps a client's treatment_charges ledger in sync with whatever visit,
 * appointment, or AI-confirmed plan session drove it: one charge row per
 * source record, created/updated/deleted alongside that record so
 * ClientFinancialSummaryService's totals never drift from what's actually on
 * the client's timeline.
 */
class TreatmentChargeService
{
    /**
     * Replaces every charge row for a source with exactly the line items
     * given -- the caller (visit/appointment/AI-plan-session save) always
     * sends its full current set of procedures (plus any discount, itself
     * just a negative-amount item), so a delete-then-insert keeps this
     * source's rows an exact mirror of that set without needing to diff
     * individual items.
     *
     * @param  array<int, array{description?: ?string, amount: float}>  $items
     */
    public function syncItems(Client $client, string $sourceType, int $sourceId, array $items): void
    {
        $this->deleteForSource($sourceType, $sourceId);

        if (empty($items)) {
            return;
        }

        $client->treatmentCharges()->createMany(
            collect($items)->map(fn (array $item) => [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'amount' => (float) $item['amount'],
                'description' => $item['description'] ?? null,
            ])->all()
        );
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
     * duplicated or orphaned.
     */
    public function retarget(string $fromType, int $fromId, string $toType, int $toId): void
    {
        TreatmentCharge::query()
            ->where('source_type', $fromType)
            ->where('source_id', $fromId)
            ->update(['source_type' => $toType, 'source_id' => $toId]);
    }

    /**
     * An appointment's charge may have been created under either "ai_plan"
     * (auto-computed at plan confirm) or "appointment" (added/edited later) --
     * callers acting on the appointment itself (delete, no-show) don't need to
     * know which, so this clears both possibilities for that appointment id.
     */
    public function deleteAllForAppointment(int $appointmentId): void
    {
        $this->deleteForSource(TreatmentCharge::SOURCE_AI_PLAN, $appointmentId);
        $this->deleteForSource(TreatmentCharge::SOURCE_APPOINTMENT, $appointmentId);
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
