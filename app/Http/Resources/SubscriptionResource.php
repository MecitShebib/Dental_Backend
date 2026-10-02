<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'specialty_id' => $this->specialty_id,
            'specialty_key' => $this->whenLoaded('specialty', fn () => $this->specialty?->key),
            'plan_name' => $this->plan_name,
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'ends_at' => $this->ends_at?->format('Y-m-d'),
            'max_doctors' => $this->max_doctors,
            'max_assistants' => $this->max_assistants,
            'active_users' => $this->active_users,
            'max_ai_tokens' => $this->max_ai_tokens,
            'features' => $this->enabledFeatures(),
            'ai_tokens_used' => $this->ai_tokens_used,
            'price' => (float) ($this->price ?? 0),
            'notes' => $this->notes,
            'is_currently_active' => $this->isCurrentlyActive(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
