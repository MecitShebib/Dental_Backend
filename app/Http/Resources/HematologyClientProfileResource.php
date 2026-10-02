<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HematologyClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'blood_type' => $this->blood_type,
            'rh' => $this->rh,
            'primary_diagnosis' => $this->primary_diagnosis,
            'diagnosis_notes' => $this->diagnosis_notes,
            'anticoagulant' => $this->anticoagulant,
            'inr_target_min' => $this->inr_target_min,
            'inr_target_max' => $this->inr_target_max,
            'splenectomy' => $this->splenectomy,
            'chemo_protocol' => $this->chemo_protocol,
            'chemo_cycles_planned' => $this->chemo_cycles_planned,
            'chemo_cycles_completed' => $this->chemo_cycles_completed,
            'bleeding_history' => $this->bleeding_history,
        ];
    }
}
