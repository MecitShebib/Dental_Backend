<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryPurchaseOrderRequest;
use App\Http\Requests\Inventory\UpdateInventoryPurchaseOrderStatusRequest;
use App\Http\Resources\InventoryPurchaseOrderResource;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryPurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = InventoryPurchaseOrder::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('inventory_item_id'), fn ($query) => $query->where('inventory_item_id', $request->integer('inventory_item_id')))
            ->with('item')
            ->latest('created_at')
            ->get();

        return $this->success(InventoryPurchaseOrderResource::collection($orders));
    }

    public function store(StoreInventoryPurchaseOrderRequest $request, InventoryItem $item, InventoryService $inventory)
    {
        $data = $request->validated();

        $order = $inventory->createPurchaseOrder(
            $item,
            (float) $data['quantity'],
            isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
            $data['notes'] ?? null,
            $request->user()->id,
        );

        return $this->success(InventoryPurchaseOrderResource::make($order->load('item')), 'Purchase order created successfully.', 201);
    }

    public function updateStatus(UpdateInventoryPurchaseOrderStatusRequest $request, InventoryPurchaseOrder $purchaseOrder, InventoryService $inventory)
    {
        $order = $inventory->updatePurchaseOrderStatus($purchaseOrder, $request->validated('status'), $request->user()->id);

        return $this->success(InventoryPurchaseOrderResource::make($order->load('item')), 'Purchase order updated successfully.');
    }
}
