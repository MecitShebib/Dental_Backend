<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSurgeryOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'operated_at' => $this->operated_at?->toDateString(),
            'operation_name' => $this->operation_name,
            'duration_minutes' => $this->duration_minutes,
            'anesthesia_type' => $this->anesthesia_type,
            'surgeon_notes' => $this->surgeon_notes,
            'complications' => $this->complications,
        ];
    }
}
