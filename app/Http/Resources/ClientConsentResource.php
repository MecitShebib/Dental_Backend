<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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
            // Signed URL, not a plain Storage one -- the signature (a form
            // of biometric-adjacent personal data) lives on the private disk.
            'signature_url' => URL::temporarySignedRoute('client-consents.signature', now()->addMinutes(60), ['consent' => $this->id]),
            'signed_at' => optional($this->signed_at)->toDateTimeString(),
            'signed_by' => $this->creator?->name,
            'client_name' => $this->client?->name,
            'company_name' => $this->client?->company?->name,
            'visit_id' => $this->visit_id,
        ];
    }
}
