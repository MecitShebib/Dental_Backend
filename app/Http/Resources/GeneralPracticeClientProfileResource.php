<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralPracticeClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'chronic_conditions' => $this->chronic_conditions ?? [],
            'current_medications' => $this->current_medications,
            'allergies' => $this->allergies,
            'blood_type' => $this->blood_type,
            'rh' => $this->rh,
            'smoking' => $this->smoking,
            'alcohol' => $this->alcohol,
            'height_cm' => $this->height_cm,
            'adult_vaccination_status' => $this->adult_vaccination_status ?? [],
        ];
    }
}
