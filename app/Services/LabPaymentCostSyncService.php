<?php

namespace App\Services;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Enums\ExpenseCategory;
use App\Models\CariTransaction;
use App\Models\Expense;
use App\Models\FundTransaction;
use App\Models\LabPayment;

/**
 * Mirrors a LabPayment into the accounting module as a `lab_fees`-category
 * Expense (and therefore the fund ledger via FundTransactionService) --
 * one Expense per payment, not per lab case, so a case that's paid off in
 * installments shows each installment as its own accounting entry. This
 * replaced LabCaseCostSyncService's old behaviour of expensing a case's
 * *entire* lab_cost the moment it was set, which left no room for partial
 * payments or an outstanding balance. Also posts the payment (credit) side
 * to the lab partner's cari ledger -- see LabCaseCariSyncService for the
 * matching invoice (debit) side posted when the case's cost is set.
 */
class LabPaymentCostSyncService
{
    public function __construct(
        protected FundTransactionService $fundTransactions,
        protected CariLedgerService $cariLedger,
    ) {}

    public function record(LabPayment $labPayment, int $actingUserId): void
    {
        $labPayment->loadMissing(['labCase.client.company', 'labCase.labPartner']);
        $labCase = $labPayment->labCase;

        $description = "Lab payment: {$labCase->work_type->value} for {$labCase->client->name}";
        $vendorName = $labCase->labPartner?->name;

        $expense = $labCase->client->company->expenses()->create([
            'category' => ExpenseCategory::LabFees,
            'vendor_name' => $vendorName,
            'amount' => $labPayment->amount,
            'expense_date' => $labPayment->payment_date,
            'description' => $description,
            'created_by' => $actingUserId,
            'updated_by' => $actingUserId,
        ]);

        $this->fundTransactions->post(
            $labCase->client->company,
            FundTransaction::SOURCE_EXPENSE,
            $expense->id,
            -1 * (float) $labPayment->amount,
            $description,
            $labPayment->payment_date,
            $actingUserId,
        );

        if ($labCase->labPartner) {
            $this->cariLedger->post(
                $labCase->client->company,
                $labCase->labPartner,
                0,
                (float) $labPayment->amount,
                CariCurrency::TRY->value,
                1,
                CariTransactionType::Payment->value,
                $description,
                null,
                $labPayment->payment_date?->toDateString(),
                null,
                CariTransaction::SOURCE_LAB_PAYMENT,
                $labPayment->id,
                null,
                $actingUserId,
            );
        }

        $labPayment->update(['expense_id' => $expense->id]);
    }

    public function remove(LabPayment $labPayment): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_LAB_PAYMENT, $labPayment->id);

        if (! $labPayment->expense_id) {
            return;
        }

        $expenseId = $labPayment->expense_id;
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_EXPENSE, $expenseId);
        Expense::query()->where('id', $expenseId)->delete();
    }
}
