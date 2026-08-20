<?php

namespace App\Services;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Models\CariTransaction;
use App\Models\LabCase;

/**
 * Mirrors a LabCase's agreed cost into the lab partner's cari ledger as an
 * "invoice" (debit) entry -- the counterpart to LabPaymentCariSyncService's
 * payment (credit) entries, so a lab partner's cari balance matches exactly
 * what LabCase::remainingBalance() already computes per case, just summed
 * across every case for that partner. A case with no partner or no cost yet
 * has nothing to post.
 */
class LabCaseCariSyncService
{
    public function __construct(protected CariLedgerService $cariLedger) {}

    public function sync(LabCase $labCase, int $actingUserId): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_LAB_CASE, $labCase->id);

        $labCase->loadMissing(['client.company', 'labPartner']);

        if (! $labCase->labPartner || (float) $labCase->lab_cost <= 0) {
            return;
        }

        $this->cariLedger->post(
            $labCase->client->company,
            $labCase->labPartner,
            (float) $labCase->lab_cost,
            0,
            CariCurrency::TRY->value,
            1,
            CariTransactionType::Invoice->value,
            "Lab case: {$labCase->work_type->value} for {$labCase->client->name}",
            $labCase->sent_date?->toDateString(),
            null,
            null,
            CariTransaction::SOURCE_LAB_CASE,
            $labCase->id,
            null,
            $actingUserId,
        );
    }

    public function remove(LabCase $labCase): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_LAB_CASE, $labCase->id);
    }
}
