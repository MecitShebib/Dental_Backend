<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricsVaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'administered_at' => $this->administered_at?->toDateString(),
            'vaccine' => $this->vaccine,
            'dose_number' => $this->dose_number,
            'lot_number' => $this->lot_number,
            'notes' => $this->notes,
        ];
    }
}
