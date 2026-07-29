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
    public function sync(Client $client, string $sourceType, int $sourceId, ?float $amount, ?string $description = null): void
    {
        if ($amount === null || $amount <= 0) {
            $this->deleteForSource($sourceType, $sourceId);

            return;
        }

        TreatmentCharge::query()->updateOrCreate(
            ['client_id' => $client->id, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['amount' => $amount, 'description' => $description]
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
}
