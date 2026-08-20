<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiConversationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'role' => $this->role,
            'content' => $this->content,
            'image_urls' => $this->image_urls,
            'options' => $this->options,
            'ready_for_plan' => (bool) $this->ready_for_plan,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
