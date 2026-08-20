<?php

namespace App\Services;

use App\Models\CariParty;
use App\Models\CariTransaction;
use App\Models\Company;
use App\Models\LabPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the cari_transactions ledger in sync with whatever expense, lab
 * case/payment, or manual entry drove it -- one or more rows per source
 * record, mirroring FundTransactionService's post/updateForSource/
 * deleteForSource pattern for the single company-wide fund ledger, but keyed
 * per counterparty (partyable) instead of company-wide, and carrying
 * separate debit/credit columns plus a currency instead of one signed amount.
 */
class CariLedgerService
{
    public function post(
        Company $company,
        Model $partyable,
        float $debit,
        float $credit,
        string $currency,
        float $exchangeRate,
        string $transactionType,
        ?string $description = null,
        ?string $invoiceDate = null,
        ?string $paymentDate = null,
        ?string $expenseCategory = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $referenceNumber = null,
        ?int $createdBy = null,
    ): CariTransaction {
        return $company->cariTransactions()->create([
            'partyable_type' => $partyable->getMorphClass(),
            'partyable_id' => $partyable->getKey(),
            'invoice_date' => $invoiceDate,
            'payment_date' => $paymentDate,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'transaction_type' => $transactionType,
            'expense_category' => $expenseCategory,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference_number' => $referenceNumber,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * partyable_type on the wire is always the short morph-map alias
     * ('cari_party'/'user'/'lab_partner'), never a raw class name. Shared by
     * CariTransactionController (manual entries against any party) and
     * ExpenseController (optional counterparty on an expense).
     */
    public function resolvePartyable(?string $type, ?int $id): ?Model
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'cari_party' => CariParty::query()->find($id),
            'user' => User::query()->find($id),
            'lab_partner' => LabPartner::query()->find($id),
            default => null,
        };
    }

    public function updateForSource(string $sourceType, int $sourceId, array $attributes): void
    {
        CariTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update($attributes);
    }

    public function deleteForSource(string $sourceType, int $sourceId): void
    {
        CariTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }

    /**
     * "Son Durum": total debit/credit/balance for one party, broken down by
     * currency (a TRY row and a USD row never get merged into one number --
     * matches the reference screenshot's two-currency summary).
     */
    public function summary(Model $partyable): array
    {
        $totals = CariTransaction::query()
            ->where('partyable_type', $partyable->getMorphClass())
            ->where('partyable_id', $partyable->getKey())
            ->selectRaw('currency, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('currency')
            ->get()
            ->keyBy(fn ($row) => $row->currency instanceof \BackedEnum ? $row->currency->value : $row->currency);

        return collect(['TRY', 'USD'])->map(function (string $currency) use ($totals) {
            $row = $totals->get($currency);
            $debit = round((float) ($row->total_debit ?? 0), 2);
            $credit = round((float) ($row->total_credit ?? 0), 2);
            $balance = round($debit - $credit, 2);

            return [
                'currency' => $currency,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => abs($balance),
                'status' => $balance > 0 ? 'debtor' : ($balance < 0 ? 'creditor' : 'settled'),
            ];
        })->values()->all();
    }
}
