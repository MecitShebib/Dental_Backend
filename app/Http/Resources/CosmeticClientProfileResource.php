<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CosmeticClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'fitzpatrick_skin_type' => $this->fitzpatrick_skin_type,
            'aesthetic_goals' => $this->aesthetic_goals,
            'areas_of_interest' => $this->areas_of_interest ?? [],
            'previous_procedures' => $this->previous_procedures,
            'allergies' => $this->allergies,
            'keloid_tendency' => $this->keloid_tendency,
            'skincare_products' => $this->skincare_products,
            'isotretinoin_use' => $this->isotretinoin_use,
            'pregnancy_breastfeeding' => $this->pregnancy_breastfeeding,
        ];
    }
}
