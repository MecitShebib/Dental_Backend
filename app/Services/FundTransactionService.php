<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FundTransaction;
use Illuminate\Support\Carbon;

/**
 * Keeps the company fund ledger (fund_transactions) in sync with whatever
 * expense, capital movement, salary advance, or salary payment drove it --
 * one row per source record, mirroring how TreatmentChargeService keeps
 * treatment_charges in sync with a visit/appointment/AI-plan session.
 */
class FundTransactionService
{
    public function post(
        Company $company,
        string $sourceType,
        ?int $sourceId,
        float $amount,
        ?string $description,
        string|Carbon $occurredOn,
        ?int $createdBy,
    ): FundTransaction {
        return $company->fundTransactions()->create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'description' => $description,
            'occurred_on' => $occurredOn,
            'created_by' => $createdBy,
        ]);
    }

    public function updateForSource(
        string $sourceType,
        int $sourceId,
        float $amount,
        ?string $description,
        string|Carbon $occurredOn,
    ): void {
        FundTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update([
                'amount' => $amount,
                'description' => $description,
                'occurred_on' => $occurredOn,
            ]);
    }

    public function deleteForSource(string $sourceType, int $sourceId): void
    {
        FundTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }
}
