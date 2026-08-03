<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'visit_id' => $this->visit_id,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'notes' => $this->notes,
            'invoice_id' => $this->whenLoaded('invoice', fn () => $this->invoice?->id),
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->formattedNumber()),
        ];
    }
}
