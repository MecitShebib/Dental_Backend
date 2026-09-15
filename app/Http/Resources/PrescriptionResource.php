<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'specialty_id' => $this->specialty_id,
            'specialty_key' => $this->whenLoaded('specialty', fn () => $this->specialty?->key),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', fn () => $this->doctor->name),
            // Signed rather than a plain Storage URL, same private-disk/KVKK
            // convention as XrayImageResource -- null when the doctor never
            // saved one, so the printed prescription just leaves that box
            // blank (see Settings > Signature & Stamp).
            'doctor_signature_url' => $this->whenLoaded('doctor', fn () => $this->doctor->signature_path
                ? URL::temporarySignedRoute('users.signature-file', now()->addMinutes(60), ['user' => $this->doctor_id])
                : null),
            'doctor_stamp_url' => $this->whenLoaded('doctor', fn () => $this->doctor->stamp_path
                ? URL::temporarySignedRoute('users.stamp-file', now()->addMinutes(60), ['user' => $this->doctor_id])
                : null),
            'appointment_id' => $this->appointment_id,
            'prescribed_date' => $this->prescribed_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'medication_name' => $item->medication_name,
                'dosage_instruction' => $item->dosage_instruction,
                'instructions' => $item->instructions,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
