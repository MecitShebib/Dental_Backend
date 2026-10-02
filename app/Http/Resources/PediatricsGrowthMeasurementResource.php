<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricsGrowthMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'measured_at' => $this->measured_at?->toDateString(),
            'weight_kg' => $this->weight_kg,
            'height_cm' => $this->height_cm,
            'head_circumference_cm' => $this->head_circumference_cm,
            'notes' => $this->notes,
        ];
    }
}
