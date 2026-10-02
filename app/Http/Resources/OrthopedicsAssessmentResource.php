<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthopedicsAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'assessed_at' => $this->assessed_at?->toDateString(),
            'pain_vas' => $this->pain_vas,
            'rom_measurements' => $this->rom_measurements ?? [],
            'notes' => $this->notes,
        ];
    }
}
