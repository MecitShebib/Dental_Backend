<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'name' => $this->name,
            'unit' => $this->unit,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'reorder_threshold' => $this->reorder_threshold !== null ? (float) $this->reorder_threshold : null,
            'reorder_quantity' => $this->reorder_quantity !== null ? (float) $this->reorder_quantity : null,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'supplier_name' => $this->supplier_name,
            'supplier_contact' => $this->supplier_contact,
            'needs_reorder' => $this->needsReorder(),
        ];
    }
}
