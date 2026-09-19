<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'status' => $this->status,
            // Empty means unrestricted (offered from every specialty).
            'specialty_ids' => $this->whenLoaded('specialties', fn () => $this->specialties->pluck('id')),
            'specialty_keys' => $this->whenLoaded('specialties', fn () => $this->specialties->pluck('key')),
            'users_count' => $this->whenCounted('users'),
            'clients_count' => $this->whenCounted('clients'),
            'created_at' => $this->created_at,
        ];
    }
}
