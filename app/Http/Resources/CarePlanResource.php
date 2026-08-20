<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'specialty_key' => $this->whenLoaded('specialty', fn () => $this->specialty?->key),
            'client_id' => $this->client_id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => optional($this->whenLoaded('doctor'))->name,
            'title' => $this->title,
            'summary' => $this->summary,
            'status' => $this->status,
            'sessions' => $this->whenLoaded('sessions', fn () => CarePlanSessionResource::collection($this->sessions)),
        ];
    }
}
