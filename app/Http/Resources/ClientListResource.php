<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nextAppointment = $this->appointments->first();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_code' => $this->client_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'city' => $this->city,
            'status' => $this->status?->value ?? $this->status,
            'last_visit_at' => optional($this->last_visit_at)->toDateTimeString(),
            'next_appointment' => $nextAppointment ? AppointmentResource::make($nextAppointment) : null,
        ];
    }
}
