<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarePlanSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'session_index' => $this->session_index,
            'title' => $this->title,
            'notes' => $this->notes,
            'clinical_data' => $this->clinical_data,
            'appointment' => $this->whenLoaded('appointment', fn () => AppointmentResource::make($this->appointment)),
        ];
    }
}
