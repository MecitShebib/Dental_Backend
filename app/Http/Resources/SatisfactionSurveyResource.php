<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SatisfactionSurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name,
            'visit_id' => $this->visit_id,
            'rating' => $this->rating,
            'wait_time_rating' => $this->wait_time_rating,
            'staff_rating' => $this->staff_rating,
            'cleanliness_rating' => $this->cleanliness_rating,
            'comment' => $this->comment,
            'invite_sent_at' => optional($this->invite_sent_at)->toDateTimeString(),
            'submitted_at' => optional($this->submitted_at)->toDateTimeString(),
        ];
    }
}
