<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'quantity' => (float) $this->quantity,
            'reason' => $this->reason,
            'expense_id' => $this->expense_id,
            'occurred_on' => optional($this->occurred_on)->toDateString(),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
