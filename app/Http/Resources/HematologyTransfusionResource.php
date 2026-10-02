<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HematologyTransfusionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'transfused_at' => $this->transfused_at?->toDateString(),
            'product' => $this->product,
            'units' => $this->units,
            'reaction' => $this->reaction,
            'reaction_notes' => $this->reaction_notes,
        ];
    }
}
