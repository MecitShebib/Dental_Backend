<?php

namespace App\Services;

use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventorySale;
use App\Models\TreatmentCharge;
use Illuminate\Support\Facades\DB;

/**
 * "Sell one or more inventory items directly to a patient" -- the Client
 * Details page's "Add From Inventory" button. Goes through the same two
 * services every other billable/costed event in this app already uses
 * rather than inventing parallel plumbing: InventoryService::recordTransaction()
 * for the stock ledger side (which also reuses its existing "not enough
 * stock" guard), and TreatmentChargeService::syncItems() for the billing
 * side (so ClientFinancialSummaryService, the Payments tab, and Reports >
 * Patient Debts all pick the charge up with zero extra code). Sale revenue
 * itself needs no Fund-posting here: a treatment_charges row only becomes
 * real cash in Accounting once the patient actually pays, exactly like
 * every other charge type -- see InventoryPurchaseCostSyncService for the
 * one accounting gap (purchase cost) this feature set actually had to close.
 */
class InventorySaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected TreatmentChargeService $treatmentCharges,
    ) {}

    /**
     * @param  array<int, array{inventory_item_id: int, quantity: float}>  $items
     */
    public function create(Client $client, array $items, int $actingUserId): InventorySale
    {
        return DB::transaction(function () use ($client, $items, $actingUserId) {
            $sale = InventorySale::create([
                'company_id' => $client->company_id,
                'client_id' => $client->id,
                'branch_id' => $client->branch_id,
                'created_by' => $actingUserId,
            ]);

            $chargeItems = [];

            foreach ($items as $line) {
                $item = InventoryItem::query()->where('company_id', $client->company_id)->findOrFail($line['inventory_item_id']);
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) ($item->unit_price ?? 0);
                $lineTotal = round($unitPrice * $quantity, 2);

                // Reuses recordTransaction()'s existing "not enough stock on
                // hand" guard -- a manual sale is never allowed to oversell,
                // unlike auto-consumption from a treatment which clamps at
                // zero instead of blocking clinical work.
                $this->inventory->recordTransaction(
                    $item,
                    'out',
                    $quantity,
                    "Sold to patient: {$client->name}",
                    null,
                    now()->toDateString(),
                    $actingUserId,
                );

                $sale->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $lineTotal,
                ]);

                $chargeItems[] = [
                    'description' => $item->name,
                    'amount' => $lineTotal,
                ];
            }

            $this->treatmentCharges->syncItems($client, TreatmentCharge::SOURCE_INVENTORY_SALE, $sale->id, $chargeItems);

            return $sale->load('items.inventoryItem');
        });
    }
}
