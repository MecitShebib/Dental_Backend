<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connected' => true,
            'provider' => $this->provider,
            'client_id' => $this->client_id,
            'accounts_base_url' => $this->accounts_base_url,
            'api_base_url' => $this->api_base_url,
            'status' => $this->status,
            'connected_at' => optional($this->connected_at)->toDateTimeString(),
            'last_error' => $this->last_error,
        ];
    }
}
