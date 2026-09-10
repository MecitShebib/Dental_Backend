<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class XrayImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            // Signed rather than a plain Storage URL -- the file lives on
            // the private disk (KVKK: no unauthenticated access to X-rays).
            // 60 minutes balances a stale-tab <img> not breaking against
            // bounding how long a leaked link stays usable.
            'image_url' => URL::temporarySignedRoute('xray-images.file', now()->addMinutes(60), ['xrayImage' => $this->id]),
            'original_filename' => $this->original_filename,
            'notes' => $this->notes,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
