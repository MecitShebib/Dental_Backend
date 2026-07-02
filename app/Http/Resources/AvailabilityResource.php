<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this['doctor_id'],
            'date' => $this['date'],
            'slots' => collect($this['slots'])->map(function (array $slot) {
                return [
                    'time' => $slot['time'],
                    'status' => $slot['status'],
                    'appointment' => $slot['appointment'] ? AppointmentResource::make($slot['appointment']) : null,
                ];
            })->values(),
        ];
    }
}
