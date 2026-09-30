<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventorySaleRequest;
use App\Http\Resources\InventorySaleResource;
use App\Models\Client;
use App\Services\InventorySaleService;

class InventorySaleController extends Controller
{
    public function store(StoreInventorySaleRequest $request, Client $client, InventorySaleService $inventorySale)
    {
        $sale = $inventorySale->create($client, $request->validated('items'), $request->user()->id);

        return $this->success(InventorySaleResource::make($sale), 'Items sold to patient successfully.', 201);
    }
}
