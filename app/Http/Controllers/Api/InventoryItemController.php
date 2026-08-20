<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\StoreInventoryTransactionRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $items = InventoryItem::query()
            ->with('branch')
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereNotNull('reorder_threshold')->whereColumn('quantity_on_hand', '<=', 'reorder_threshold'))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->orderBy('name')
            ->get();

        return $this->success(InventoryItemResource::collection($items));
    }

    public function store(StoreInventoryItemRequest $request)
    {
        $item = InventoryItem::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'status' => $request->validated('status') ?? 'active',
        ]);

        return $this->success(InventoryItemResource::make($item), 'Inventory item created successfully.', 201);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item)
    {
        $item->update($request->validated());

        return $this->success(InventoryItemResource::make($item), 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $item)
    {
        $item->delete();

        return $this->success(null, 'Inventory item deleted successfully.');
    }

    public function transactions(InventoryItem $item)
    {
        return $this->success(
            InventoryTransactionResource::collection($item->transactions()->latest('occurred_on')->latest('id')->get())
        );
    }

    public function storeTransaction(StoreInventoryTransactionRequest $request, InventoryItem $item, InventoryService $inventory)
    {
        $data = $request->validated();

        $transaction = $inventory->recordTransaction(
            $item,
            $data['type'],
            (float) $data['quantity'],
            $data['reason'] ?? null,
            $data['expense_id'] ?? null,
            $data['occurred_on'],
            $request->user()->id,
        );

        return $this->success([
            'transaction' => InventoryTransactionResource::make($transaction),
            'item' => InventoryItemResource::make($item->fresh()),
        ], 'Transaction recorded successfully.', 201);
    }
}
