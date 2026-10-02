<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HematologyBloodCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'measured_at' => $this->measured_at?->toDateString(),
            'hb' => $this->hb,
            'hct' => $this->hct,
            'wbc' => $this->wbc,
            'plt' => $this->plt,
            'mcv' => $this->mcv,
            'ferritin' => $this->ferritin,
            'inr' => $this->inr,
            'notes' => $this->notes,
        ];
    }
}
