<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'invoice_number' => $this->invoice_number,
            'formatted_number' => $this->formattedNumber(),
            'amount' => (float) $this->amount,
            'issued_date' => $this->issued_date?->format('Y-m-d'),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'phone' => $this->client->phone,
            ]),
            'payment' => $this->whenLoaded('payment', fn () => [
                'id' => $this->payment->id,
                'payment_method' => $this->payment->payment_method?->value,
                'notes' => $this->payment->notes,
            ]),
        ];
    }
}
