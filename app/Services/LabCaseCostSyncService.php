<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\FundTransaction;
use App\Models\LabCase;

/**
 * Keeps a lab case's `lab_cost` mirrored into the accounting module as an
 * `lab_fees`-category Expense (and therefore the fund ledger via
 * FundTransactionService) -- created the moment a cost is set, kept in sync
 * on edit, removed if the cost is cleared or the case itself is deleted.
 */
class LabCaseCostSyncService
{
    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function sync(LabCase $labCase, int $actingUserId): void
    {
        $labCase->loadMissing(['client.company', 'labPartner']);
        $hasCost = $labCase->lab_cost !== null && (float) $labCase->lab_cost > 0;

        if (! $hasCost) {
            $this->removeExpense($labCase);

            return;
        }

        $description = "Lab case: {$labCase->work_type->value} for {$labCase->client->name}";
        $vendorName = $labCase->labPartner?->name;

        if ($labCase->expense_id) {
            $labCase->expense()->update([
                'amount' => $labCase->lab_cost,
                'expense_date' => $labCase->sent_date,
                'vendor_name' => $vendorName,
                'description' => $description,
                'updated_by' => $actingUserId,
            ]);

            $this->fundTransactions->updateForSource(
                FundTransaction::SOURCE_EXPENSE,
                $labCase->expense_id,
                -1 * (float) $labCase->lab_cost,
                $description,
                $labCase->sent_date,
            );

            return;
        }

        $expense = $labCase->client->company->expenses()->create([
            'category' => ExpenseCategory::LabFees,
            'vendor_name' => $vendorName,
            'amount' => $labCase->lab_cost,
            'expense_date' => $labCase->sent_date,
            'description' => $description,
            'created_by' => $actingUserId,
            'updated_by' => $actingUserId,
        ]);

        $this->fundTransactions->post(
            $labCase->client->company,
            FundTransaction::SOURCE_EXPENSE,
            $expense->id,
            -1 * (float) $labCase->lab_cost,
            $description,
            $labCase->sent_date,
            $actingUserId,
        );

        $labCase->update(['expense_id' => $expense->id]);
    }

    public function removeExpense(LabCase $labCase): void
    {
        if (! $labCase->expense_id) {
            return;
        }

        $expenseId = $labCase->expense_id;
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_EXPENSE, $expenseId);
        Expense::query()->where('id', $expenseId)->delete();
        $labCase->update(['expense_id' => null]);
    }
}
