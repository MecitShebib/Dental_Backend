<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connected' => true,
            'phone_number_id' => $this->phone_number_id,
            'business_account_id' => $this->business_account_id,
            'status' => $this->status,
            'connected_at' => optional($this->connected_at)->toDateTimeString(),
            'last_error' => $this->last_error,
            // Never expose the real token -- just enough for the admin to
            // recognize which credential is connected.
            'access_token_preview' => $this->access_token ? ('••••'.substr($this->access_token, -4)) : null,
        ];
    }
}
