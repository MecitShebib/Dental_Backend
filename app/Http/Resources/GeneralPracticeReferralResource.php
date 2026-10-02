<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralPracticeReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'referred_at' => $this->referred_at?->toDateString(),
            'target_specialty' => $this->target_specialty,
            'target_institution' => $this->target_institution,
            'reason' => $this->reason,
            'status' => $this->status,
        ];
    }
}
