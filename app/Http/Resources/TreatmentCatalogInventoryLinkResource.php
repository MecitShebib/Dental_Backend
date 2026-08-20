<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentCatalogInventoryLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item_name' => $this->inventoryItem?->name,
            'unit' => $this->inventoryItem?->unit,
            'quantity_per_use' => (float) $this->quantity_per_use,
        ];
    }
}
