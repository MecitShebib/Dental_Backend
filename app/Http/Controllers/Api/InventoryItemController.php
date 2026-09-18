<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\StoreInventoryTransactionRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\InventoryItem;
use App\Models\Specialty;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $items = InventoryItem::query()
            ->with(['branch', 'specialty'])
            // An item with no branch_id/specialty_id assigned yet (pre-dates
            // this scoping) stays visible from every branch/specialty rather
            // than silently disappearing.
            ->when($request->filled('branch_id'), fn ($query) => $query->where(fn ($q) => $q->where('branch_id', $request->integer('branch_id'))->orWhereNull('branch_id')))
            ->when($specialtyId, fn ($query) => $query->where(fn ($q) => $q->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereNotNull('reorder_threshold')->whereColumn('quantity_on_hand', '<=', 'reorder_threshold'))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->string('name').'%'))
            ->orderBy('name')
            ->get();

        return $this->success(InventoryItemResource::collection($items));
    }

    public function store(StoreInventoryItemRequest $request)
    {
        $actingUser = $request->user();
        $data = $request->validated();

        // Same rule as everywhere else: a user with their own branch_id/
        // specialty_id (doctor, or staff assigned to one branch) always
        // creates records there, overriding whatever the request sent.
        // Otherwise falls back to whatever the request/frontend (its
        // currently active branch/specialty) explicitly provided.
        $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);
        $specialtyId = $actingUser->is_doctor
            ? $actingUser->specialty_id
            : ($data['specialty_id'] ?? null);

        $item = InventoryItem::create([
            ...$data,
            'branch_id' => $branchId,
            'specialty_id' => $specialtyId,
            'company_id' => $actingUser->company_id,
            'status' => $data['status'] ?? 'active',
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
