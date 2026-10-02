<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\FundTransaction;
use App\Models\InventoryItem;
use App\Models\InventorySale;
use App\Models\TreatmentCharge;
use Illuminate\Support\Facades\DB;

/**
 * "Sell one or more inventory items" -- the Inventory page's "New Sale
 * Order" button. Three outcomes depending on what the staff member picks,
 * each going through the same ledgers every other billable/costed event in
 * this app already uses instead of inventing parallel plumbing:
 *
 *  - No patient picked: a walk-in retail sale. Revenue posts straight to
 *    the company fund via FundTransactionService, same as any other cash
 *    sale -- no client, no charge.
 *  - Patient picked + paid on the spot: same fund posting as above, but the
 *    sale stays linked to the patient for their own record.
 *  - Patient picked + not paid: no fund posting yet -- instead becomes an
 *    ordinary TreatmentCharge against that patient (their debt), exactly
 *    like every other charge type. It only becomes real cash once they
 *    later make an actual Payment, same as any other charge.
 *
 * Stock always decrements via InventoryService::recordTransaction()
 * regardless of which of the three paths this is -- selling an item is
 * selling it, whether or not it's paid for yet.
 */
class InventorySaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected TreatmentChargeService $treatmentCharges,
        protected FundTransactionService $fundTransactions,
    ) {}

    /**
     * @param  array<int, array{inventory_item_id: int, quantity: float}>  $items
     */
    public function create(Company $company, ?Client $client, bool $isPaid, array $items, int $actingUserId): InventorySale
    {
        return DB::transaction(function () use ($company, $client, $isPaid, $items, $actingUserId) {
            $sale = InventorySale::create([
                'company_id' => $company->id,
                'client_id' => $client?->id,
                'branch_id' => $client?->branch_id,
                'is_paid' => $client ? $isPaid : null,
                'created_by' => $actingUserId,
            ]);

            $chargeItems = [];
            $itemNames = [];
            $grandTotal = 0.0;

            foreach ($items as $line) {
                $item = InventoryItem::query()->where('company_id', $company->id)->findOrFail($line['inventory_item_id']);
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
                    $client ? "Sold to patient: {$client->name}" : 'Sold (walk-in)',
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

                $chargeItems[] = ['description' => $item->name, 'amount' => $lineTotal];
                $itemNames[] = $item->name;
                $grandTotal += $lineTotal;
            }

            // No client, or a client who paid on the spot: this is realized
            // cash, so it posts to the fund immediately. A client who hasn't
            // paid yet becomes patient debt instead -- see the class doc.
            if ($client === null || $isPaid) {
                $this->fundTransactions->post(
                    $company,
                    FundTransaction::SOURCE_INVENTORY_SALE,
                    $sale->id,
                    round($grandTotal, 2),
                    'Inventory sale: '.implode(', ', $itemNames),
                    now()->toDateString(),
                    $actingUserId,
                );
            } else {
                $this->treatmentCharges->syncItems($client, TreatmentCharge::SOURCE_INVENTORY_SALE, $sale->id, $chargeItems);
            }

            return $sale->load('items.inventoryItem', 'client');
        });
    }
}
