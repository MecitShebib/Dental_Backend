<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name,
            'phone_number' => $this->phone_number,
            'direction' => $this->direction,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'recording_url' => $this->recording_url,
            'occurred_at' => optional($this->occurred_at)->toDateTimeString(),
            'notes' => $this->notes,
            'followed_up_at' => optional($this->followed_up_at)->toDateTimeString(),
            'needs_follow_up' => $this->needsFollowUp(),
            'created_by' => $this->creator?->name,
        ];
    }
}
