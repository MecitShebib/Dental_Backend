<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => optional($this->whenLoaded('doctor'))->name,
            'appointment_id' => $this->appointment_id,
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'duration_minutes' => $this->duration_minutes,
            'summary' => $this->summary,
            'notes' => $this->notes,
            'attendance_status' => $this->attendance_status?->value ?? $this->attendance_status,
        ];
    }
}
