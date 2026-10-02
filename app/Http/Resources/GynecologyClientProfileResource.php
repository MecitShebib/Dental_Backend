<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GynecologyClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'last_menstrual_period' => $this->last_menstrual_period?->toDateString(),
            'gravida' => $this->gravida,
            'para' => $this->para,
            'abortus' => $this->abortus,
            'blood_type' => $this->blood_type,
            'rh' => $this->rh,
            'cycle_length_days' => $this->cycle_length_days,
            'cycle_regularity' => $this->cycle_regularity,
            'contraception_method' => $this->contraception_method,
            'last_pap_smear_date' => $this->last_pap_smear_date?->toDateString(),
            'menopause_status' => $this->menopause_status,
            'previous_delivery_types' => $this->previous_delivery_types ?? [],
            'notes' => $this->notes,
        ];
    }
}
