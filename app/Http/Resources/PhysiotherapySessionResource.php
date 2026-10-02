<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhysiotherapySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'session_date' => $this->session_date?->toDateString(),
            'session_number' => $this->session_number,
            'pain_vas' => $this->pain_vas,
            'rom_measurements' => $this->rom_measurements ?? [],
            'muscle_strength' => $this->muscle_strength ?? [],
            'modalities' => $this->modalities ?? [],
            'notes' => $this->notes,
        ];
    }
}
