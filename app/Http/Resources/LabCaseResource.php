<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->name),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', fn () => $this->doctor->name),
            'lab_partner_id' => $this->lab_partner_id,
            'lab_partner_name' => $this->whenLoaded('labPartner', fn () => $this->labPartner?->name),
            'appointment_id' => $this->appointment_id,
            'appointment_date' => $this->whenLoaded('appointment', fn () => $this->appointment?->date?->format('Y-m-d')),
            'work_type' => $this->work_type?->value,
            'teeth' => $this->teeth ?? [],
            'material' => $this->material,
            'shade' => $this->shade,
            'status' => $this->status?->value,
            'sent_date' => $this->sent_date?->format('Y-m-d'),
            'expected_return_date' => $this->expected_return_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'lab_cost' => $this->lab_cost !== null ? (float) $this->lab_cost : null,
            'expense_id' => $this->expense_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
