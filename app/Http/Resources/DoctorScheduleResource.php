<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->doctor_id,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'slot_minutes' => $this->slot_minutes,
            'working_days' => $this->whenLoaded('workingDays', fn () => $this->workingDays->pluck('weekday')->map(fn ($day) => $day->value)->values(), []),
        ];
    }
}
