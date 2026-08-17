<?php

namespace App\Http\Resources;

use App\Services\AppointmentActionStateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = app(AppointmentActionStateService::class)->metadata($this->resource);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => optional($this->whenLoaded('client'))->name,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => optional($this->whenLoaded('doctor'))->name,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'duration_minutes' => $this->duration_minutes,
            'notes' => $this->notes,
            'planned_summary' => $this->planned_summary,
            'planned_notes' => $this->planned_notes,
            'action_state' => $meta['action_state'],
            'is_past' => $meta['is_past'],
            'is_future' => $meta['is_future'],
            'is_within_one_hour' => $meta['is_within_one_hour'],
        ];
    }
}
