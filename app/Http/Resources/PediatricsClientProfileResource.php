<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PediatricsClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'gestational_age_weeks_at_birth' => $this->gestational_age_weeks_at_birth,
            'birth_weight_g' => $this->birth_weight_g,
            'birth_length_cm' => $this->birth_length_cm,
            'birth_head_circumference_cm' => $this->birth_head_circumference_cm,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'guardian_relation' => $this->guardian_relation,
            'feeding_type' => $this->feeding_type,
            'blood_type' => $this->blood_type,
            'rh' => $this->rh,
            'allergies' => $this->allergies,
            'chronic_conditions' => $this->chronic_conditions,
            'developmental_milestones' => $this->developmental_milestones ?? [],
        ];
    }
}
