<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'treatment_plan' => $this->treatment_plan,
            'currency_code' => $this->currency_code,
            'total_services_amount' => (float) $this->total_services_amount,
            'notes' => $this->notes,
            'teeth' => $this->whenLoaded('teeth', fn () => $this->teeth->map(function ($tooth) {
                return [
                    'id' => $tooth->id,
                    'tooth_number' => $tooth->tooth_number,
                    'treatment_catalog_id' => $tooth->treatment_catalog_id,
                    'company_id' => optional($tooth->treatmentCatalog)->company_id,
                    'treatment_code' => optional($tooth->treatmentCatalog)->code,
                    'treatment_name' => optional($tooth->treatmentCatalog)->name_en
                        ?? optional($tooth->treatmentCatalog)->name_ar
                        ?? optional($tooth->treatmentCatalog)->name_tr,
                    'unit_price' => (float) $tooth->unit_price,
                    'notes' => $tooth->notes,
                ];
            })->values(), []),
        ];
    }
}
