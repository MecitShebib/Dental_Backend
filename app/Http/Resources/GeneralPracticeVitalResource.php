<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralPracticeVitalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'measured_at' => $this->measured_at?->toDateString(),
            'systolic' => $this->systolic,
            'diastolic' => $this->diastolic,
            'pulse' => $this->pulse,
            'temperature_c' => $this->temperature_c,
            'spo2' => $this->spo2,
            'blood_glucose' => $this->blood_glucose,
            'weight_kg' => $this->weight_kg,
            'notes' => $this->notes,
        ];
    }
}
