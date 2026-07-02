<?php

namespace App\Http\Resources;

use App\Services\ClientFinancialSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_code' => $this->client_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender?->value ?? $this->gender,
            'age' => $this->age,
            'date_of_birth' => optional($this->date_of_birth)->format('Y-m-d'),
            'city' => $this->city,
            'address' => $this->address,
            'medical_notes' => $this->medical_notes,
            'status' => $this->status?->value ?? $this->status,
            'last_visit_at' => optional($this->last_visit_at)->toDateTimeString(),
            'next_appointment' => $this->appointments->first() ? AppointmentResource::make($this->appointments->first()) : null,
            'financial_summary' => app(ClientFinancialSummaryService::class)->summary($this->resource),
        ];
    }
}
