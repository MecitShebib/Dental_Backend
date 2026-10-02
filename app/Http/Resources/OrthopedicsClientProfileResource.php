<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrthopedicsClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'affected_region' => $this->affected_region,
            'side' => $this->side,
            'complaint_onset_date' => $this->complaint_onset_date?->toDateString(),
            'injury_mechanism' => $this->injury_mechanism,
            'previous_surgeries_implants' => $this->previous_surgeries_implants,
            'cast_splint_status' => $this->cast_splint_status,
            'occupation_sport' => $this->occupation_sport,
            'dominant_hand' => $this->dominant_hand,
        ];
    }
}
