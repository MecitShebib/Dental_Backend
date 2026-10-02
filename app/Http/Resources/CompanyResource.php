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
            'status' => $this->status,
            'currency' => $this->currency?->value,
            'language' => $this->language?->value ?? 'tr',
            'notes' => $this->notes,
            'recall_interval_days' => $this->recall_interval_days,
            'effective_recall_interval_days' => $this->recallIntervalDays(),
            'booking_slug' => $this->booking_slug,
            'booking_url' => $this->bookingUrl(),
            'users_count' => $this->whenCounted('users', $this->users_count),
            'active_users_count' => $this->when(isset($this->active_users_count), $this->active_users_count),
            'seat_usage' => $this->when(isset($this->seat_usage), fn () => $this->seat_usage),
            'latest_active_subscription' => $this->whenLoaded('currentSubscription', function () {
                return $this->currentSubscription
                    ? SubscriptionResource::make($this->currentSubscription)
                    : null;
            }),
        ];
    }
}
