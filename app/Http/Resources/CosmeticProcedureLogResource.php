<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class CosmeticProcedureLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'performed_at' => $this->performed_at?->toDateString(),
            'procedure_type' => $this->procedure_type,
            'product_name' => $this->product_name,
            'lot_number' => $this->lot_number,
            'amount' => $this->amount,
            'unit' => $this->unit,
            'treatment_area' => $this->treatment_area,
            'before_photo_name' => $this->before_photo_original_filename,
            'before_photo_url' => $this->before_photo_path
                ? URL::temporarySignedRoute('cosmetic.procedure-logs.file', now()->addMinutes(60), ['record' => $this->id, 'field' => 'before_photo'])
                : null,
            'after_photo_name' => $this->after_photo_original_filename,
            'after_photo_url' => $this->after_photo_path
                ? URL::temporarySignedRoute('cosmetic.procedure-logs.file', now()->addMinutes(60), ['record' => $this->id, 'field' => 'after_photo'])
                : null,
            'notes' => $this->notes,
        ];
    }
}
