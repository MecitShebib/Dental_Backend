<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSurgeryFollowupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'followup_date' => $this->followup_date?->toDateString(),
            'operation_id' => $this->operation_id,
            'wound_status' => $this->wound_status,
            'drain_removed_at' => $this->drain_removed_at?->toDateString(),
            'sutures_removed_at' => $this->sutures_removed_at?->toDateString(),
            'complications' => $this->complications,
            'notes' => $this->notes,
        ];
    }
}
