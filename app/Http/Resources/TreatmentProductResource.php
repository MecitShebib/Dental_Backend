<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'scope' => $this->scope,
            'code' => $this->code,
            'name' => $this->name_en ?? $this->name_ar ?? $this->name_tr,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'name_tr' => $this->name_tr,
            'price' => (float) $this->default_price,
            'unit_price' => (float) $this->default_price,
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
