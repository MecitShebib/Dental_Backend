<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'version' => $this->version,
            'status' => $this->status,
            'notes' => $this->notes,
            'users_count' => $this->whenCounted('users', $this->users_count),
            'active_users_count' => $this->when(isset($this->active_users_count), $this->active_users_count),
            'latest_active_subscription' => $this->whenLoaded('currentSubscription', function () {
                return $this->currentSubscription
                    ? SubscriptionResource::make($this->currentSubscription)
                    : null;
            }),
        ];
    }
}
