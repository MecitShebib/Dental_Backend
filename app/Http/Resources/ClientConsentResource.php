<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClientConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'sections' => $this->sections ?? [],
            'signature_url' => Storage::disk('public')->url($this->signature_path),
            'signed_at' => optional($this->signed_at)->toDateTimeString(),
            'signed_by' => $this->creator?->name,
            'client_name' => $this->client?->name,
            'company_name' => $this->client?->company?->name,
            'visit_id' => $this->visit_id,
        ];
    }
}
