<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CapitalTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'party_name' => $this->party_name,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
