<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'specialty_id' => $this->specialty_id,
            'specialty_name' => $this->whenLoaded('specialty', fn () => $this->specialty?->brand_name),
            'title' => $this->title,
            'body' => $this->body,
            'sections' => $this->sections ?? [],
            'language' => $this->language,
            'is_active' => $this->is_active,
        ];
    }
}
