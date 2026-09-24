<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'height_cm' => $this->height_cm,
            'body_type' => $this->body_type,
            'dietary_type' => $this->dietary_type,
            'allergies' => $this->allergies ?? [],
            'chronic_conditions' => $this->chronic_conditions ?? [],
            'medications_affecting_diet' => $this->medications_affecting_diet,
            'smoking_status' => $this->smoking_status,
            'alcohol_status' => $this->alcohol_status,
            'activity_level' => $this->activity_level,
            'goal' => $this->goal,
            'target_weight_kg' => $this->target_weight_kg,
            'notes' => $this->notes,
        ];
    }
}
