<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FundTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'occurred_on' => $this->occurred_on?->format('Y-m-d'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
