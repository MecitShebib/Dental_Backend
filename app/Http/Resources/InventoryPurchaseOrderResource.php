<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryPurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'inventory_item_id' => $this->inventory_item_id,
            'item_name' => $this->item?->name,
            'unit' => $this->item?->unit,
            'quantity' => (float) $this->quantity,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'total_cost' => $this->total_cost !== null ? (float) $this->total_cost : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->creator?->name,
            'ordered_at' => optional($this->ordered_at)->toDateTimeString(),
            'received_at' => optional($this->received_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
