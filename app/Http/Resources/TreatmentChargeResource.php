<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentChargeResource extends JsonResource
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
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
