<?php

namespace App\Services;

use App\Mail\LowStockAlertMail;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryTransaction;
use App\Models\TreatmentCatalogInventoryLink;
use App\Models\TreatmentChargeInventoryConsumption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * 'in'/'out' quantities are always positive (direction comes from
     * $type); 'adjustment' takes a signed delta applied directly, for
     * correcting miscounts either way.
     */
    public function recordTransaction(
        InventoryItem $item,
        string $type,
        float $quantity,
        ?string $reason,
        ?int $expenseId,
        string $occurredOn,
        ?int $createdBy,
    ): InventoryTransaction {
        $previousQuantity = (float) $item->quantity_on_hand;

        $delta = match ($type) {
            'in' => $quantity,
            'out' => -$quantity,
            'adjustment' => $quantity,
            default => throw ValidationException::withMessages(['type' => ['Invalid transaction type.']]),
        };

        if ($type === 'out' && ($previousQuantity + $delta) < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Not enough stock on hand for this item.'],
            ]);
        }

        $transaction = InventoryTransaction::create([
            'company_id' => $item->company_id,
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'expense_id' => $expenseId,
            'occurred_on' => $occurredOn,
            'created_by' => $createdBy,
        ]);

        $item->update(['quantity_on_hand' => round($previousQuantity + $delta, 2)]);

        $this->maybeAlertLowStock($item->fresh(), $previousQuantity);

        return $transaction;
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function lowStockItems(Company $company): Collection
    {
        return InventoryItem::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereNotNull('reorder_threshold')
            ->whereColumn('quantity_on_hand', '<=', 'reorder_threshold')
            ->get();
    }

    /**
     * Edge-triggered: only fires the moment a level crosses from above the
     * threshold to at-or-below it, and resets once it's replenished back
     * above threshold -- so a company isn't emailed on every single "out"
     * transaction while an item stays low.
     */
    protected function maybeAlertLowStock(InventoryItem $item, float $previousQuantity): void
    {
        if ($item->reorder_threshold === null) {
            return;
        }

        $threshold = (float) $item->reorder_threshold;
        $wasAboveThreshold = $previousQuantity > $threshold;
        $isNowAtOrBelow = (float) $item->quantity_on_hand <= $threshold;

        if ($wasAboveThreshold && $isNowAtOrBelow) {
            $this->sendLowStockAlert($item);
            $this->autoCreatePurchaseOrder($item);

            return;
        }

        if (! $isNowAtOrBelow && $item->reorder_alert_sent_at !== null) {
            $item->update(['reorder_alert_sent_at' => null]);
        }
    }

    protected function sendLowStockAlert(InventoryItem $item): void
    {
        $company = $item->company;

        if ($company?->email) {
            Mail::to($company->email)->send(new LowStockAlertMail($item));
        }

        $item->update(['reorder_alert_sent_at' => now()]);

        Log::info('Low stock alert triggered.', [
            'inventory_item_id' => $item->id,
            'quantity_on_hand' => $item->quantity_on_hand,
            'reorder_threshold' => $item->reorder_threshold,
        ]);
    }

    /**
     * Raises a draft purchase order the admin can review/edit and mark
     * ordered -- skipped if one is already open (pending or ordered) for
     * this item, so repeatedly dipping under threshold doesn't spam
     * duplicate orders.
     */
    protected function autoCreatePurchaseOrder(InventoryItem $item): void
    {
        $hasOpenOrder = InventoryPurchaseOrder::query()
            ->where('inventory_item_id', $item->id)
            ->whereIn('status', [InventoryPurchaseOrder::STATUS_PENDING, InventoryPurchaseOrder::STATUS_ORDERED])
            ->exists();

        if ($hasOpenOrder) {
            return;
        }

        $quantity = (float) ($item->reorder_quantity ?? $item->reorder_threshold ?? 1);

        $this->createPurchaseOrder($item, $quantity, $item->unit_cost !== null ? (float) $item->unit_cost : null, null, null);
    }

    public function createPurchaseOrder(InventoryItem $item, float $quantity, ?float $unitCost, ?string $notes, ?int $createdBy): InventoryPurchaseOrder
    {
        return InventoryPurchaseOrder::create([
            'company_id' => $item->company_id,
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost !== null ? round($unitCost * $quantity, 2) : null,
            'status' => InventoryPurchaseOrder::STATUS_PENDING,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Marking an order "received" is the one status transition with a real
     * side effect -- it books an ordinary 'in' inventory_transaction for the
     * ordered quantity, so the stock level and the order's own paper trail
     * never drift apart.
     */
    public function updatePurchaseOrderStatus(InventoryPurchaseOrder $order, string $status, ?int $actingUserId): InventoryPurchaseOrder
    {
        if ($order->status === InventoryPurchaseOrder::STATUS_RECEIVED || $order->status === InventoryPurchaseOrder::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['This purchase order is already closed.'],
            ]);
        }

        if ($status === InventoryPurchaseOrder::STATUS_RECEIVED) {
            $this->recordTransaction(
                $order->item,
                'in',
                (float) $order->quantity,
                'Purchase order received',
                null,
                now()->toDateString(),
                $actingUserId,
            );
            $order->update(['status' => $status, 'received_at' => now()]);

            return $order;
        }

        if ($status === InventoryPurchaseOrder::STATUS_ORDERED) {
            $order->update(['status' => $status, 'ordered_at' => now()]);

            return $order;
        }

        $order->update(['status' => $status]);

        return $order;
    }

    /**
     * Diffs a treatment-charge source's (visit/appointment/AI-plan) current
     * catalog-linked inventory usage against what it consumed last time this
     * ran, and applies only the delta as ordinary inventory_transactions --
     * called every time TreatmentChargeService::syncItems() re-syncs that
     * source's charges (a full delete-then-recreate), so a re-sync that adds
     * one more filling doesn't double-consume material for the fillings that
     * were already there, and removing a procedure correctly restocks it.
     *
     * @param  array<int, int>  $catalogIdCounts  treatment_catalog_id => how many charge line items reference it in the new set
     */
    public function syncConsumptionForSource(int $companyId, string $autoSourceType, int $autoSourceId, array $catalogIdCounts): void
    {
        $desired = $this->desiredConsumption($companyId, $catalogIdCounts);

        $existing = TreatmentChargeInventoryConsumption::query()
            ->where('source_type', $autoSourceType)
            ->where('source_id', $autoSourceId)
            ->get()
            ->keyBy('inventory_item_id');

        $itemIds = collect($desired->keys())->merge($existing->keys())->unique();

        foreach ($itemIds as $itemId) {
            $desiredQuantity = (float) ($desired->get($itemId) ?? 0);
            $existingRow = $existing->get($itemId);
            $previousQuantity = (float) ($existingRow?->quantity ?? 0);

            if ($desiredQuantity === $previousQuantity) {
                continue;
            }

            $item = InventoryItem::query()->find($itemId);
            if (! $item) {
                continue;
            }

            $delta = $desiredQuantity - $previousQuantity;

            if ($delta > 0) {
                $this->recordAutoTransaction($item, 'out', $delta, $autoSourceType);
            } else {
                $this->recordAutoTransaction($item, 'in', abs($delta), $autoSourceType);
            }

            if ($desiredQuantity > 0) {
                TreatmentChargeInventoryConsumption::updateOrCreate(
                    ['source_type' => $autoSourceType, 'source_id' => $autoSourceId, 'inventory_item_id' => $itemId],
                    ['quantity' => $desiredQuantity],
                );
            } else {
                TreatmentChargeInventoryConsumption::query()
                    ->where('source_type', $autoSourceType)
                    ->where('source_id', $autoSourceId)
                    ->where('inventory_item_id', $itemId)
                    ->delete();
            }
        }
    }

    /**
     * Clinical work must never be blocked by a bookkeeping shortfall, so
     * auto-consumption clamps at zero (stock can go visibly negative-free,
     * i.e. floored) instead of throwing the way a manual "out" entry would.
     */
    protected function recordAutoTransaction(InventoryItem $item, string $type, float $quantity, string $autoSourceType): void
    {
        if ($quantity <= 0) {
            return;
        }

        $previousQuantity = (float) $item->quantity_on_hand;
        $delta = $type === 'out' ? -$quantity : $quantity;
        $newQuantity = max(0, round($previousQuantity + $delta, 2));

        InventoryTransaction::create([
            'company_id' => $item->company_id,
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => "Auto-consumed: {$autoSourceType}",
            'is_auto_consumption' => true,
            'occurred_on' => now()->toDateString(),
        ]);

        $item->update(['quantity_on_hand' => $newQuantity]);

        $this->maybeAlertLowStock($item->fresh(), $previousQuantity);
    }

    /**
     * @param  array<int, int>  $catalogIdCounts
     * @return \Illuminate\Support\Collection<int, float> inventory_item_id => total quantity needed
     */
    protected function desiredConsumption(int $companyId, array $catalogIdCounts): \Illuminate\Support\Collection
    {
        if (empty($catalogIdCounts)) {
            return collect();
        }

        return TreatmentCatalogInventoryLink::query()
            ->whereIn('treatment_catalog_id', array_keys($catalogIdCounts))
            ->whereHas('catalogEntry', fn ($query) => $query->where('company_id', $companyId))
            ->get()
            ->reduce(function (\Illuminate\Support\Collection $carry, TreatmentCatalogInventoryLink $link) use ($catalogIdCounts) {
                $count = $catalogIdCounts[$link->treatment_catalog_id] ?? 0;
                $quantity = (float) $link->quantity_per_use * $count;

                return $carry->put(
                    $link->inventory_item_id,
                    (float) $carry->get($link->inventory_item_id, 0) + $quantity,
                );
            }, collect());
    }
}
