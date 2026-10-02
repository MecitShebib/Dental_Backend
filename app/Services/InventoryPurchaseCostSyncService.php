<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\FundTransaction;
use App\Models\InventoryPurchaseOrder;

/**
 * Mirrors a received InventoryPurchaseOrder into the accounting module as a
 * Medical Supplies-category Expense (and therefore the fund ledger via
 * FundTransactionService) -- modeled 1:1 on LabPaymentCostSyncService, the
 * existing template for "a real-world cost event should also create an
 * Expense + post a FundTransaction". Called immediately on every purchase
 * order now (see InventoryService::createPurchaseOrder() -- creating an
 * order IS receiving it, no more manual follow-up), so this is what makes a
 * restock actually move the fund balance. See InventorySaleService for the
 * revenue side.
 */
class InventoryPurchaseCostSyncService
{
    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function record(InventoryPurchaseOrder $order, int $actingUserId): void
    {
        if ($order->total_cost === null || (float) $order->total_cost <= 0) {
            return;
        }

        $order->loadMissing('item.company');
        $company = $order->item->company;
        $description = "Inventory purchase: {$order->item->name} x {$order->quantity}";

        $expense = $company->expenses()->create([
            'category' => ExpenseCategory::DentalSupplies,
            'vendor_name' => $order->item->supplier_name,
            'amount' => $order->total_cost,
            'expense_date' => now()->toDateString(),
            'description' => $description,
            'created_by' => $actingUserId,
            'updated_by' => $actingUserId,
        ]);

        $this->fundTransactions->post(
            $company,
            FundTransaction::SOURCE_EXPENSE,
            $expense->id,
            -1 * (float) $order->total_cost,
            $description,
            now()->toDateString(),
            $actingUserId,
        );
    }
}
