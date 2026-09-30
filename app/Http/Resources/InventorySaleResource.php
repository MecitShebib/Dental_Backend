<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'total' => (float) $this->items->sum('line_total'),
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'inventory_item_id' => $item->inventory_item_id,
                'item_name' => $item->inventoryItem?->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
