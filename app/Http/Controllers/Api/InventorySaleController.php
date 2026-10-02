<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventorySaleRequest;
use App\Http\Resources\InventorySaleResource;
use App\Models\Client;
use App\Services\InventorySaleService;

class InventorySaleController extends Controller
{
    public function store(StoreInventorySaleRequest $request, InventorySaleService $inventorySale)
    {
        $client = $request->filled('client_id')
            ? Client::query()->where('company_id', $request->user()->company_id)->findOrFail($request->integer('client_id'))
            : null;

        $sale = $inventorySale->create(
            $request->user()->company,
            $client,
            $request->boolean('is_paid'),
            $request->validated('items'),
            $request->user()->id,
        );

        return $this->success(InventorySaleResource::make($sale), 'Sale recorded successfully.', 201);
    }
}
