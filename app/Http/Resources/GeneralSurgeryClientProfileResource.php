<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSurgeryClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'indication' => $this->indication,
            'planned_operation' => $this->planned_operation,
            'asa_score' => $this->asa_score,
            'previous_surgeries' => $this->previous_surgeries,
            'anticoagulant_use' => $this->anticoagulant_use,
            'allergies' => $this->allergies,
            'blood_type' => $this->blood_type,
            'rh' => $this->rh,
            'preop_checklist' => $this->preop_checklist ?? [],
        ];
    }
}
