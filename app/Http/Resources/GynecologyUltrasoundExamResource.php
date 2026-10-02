<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class GynecologyUltrasoundExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'exam_date' => $this->exam_date?->toDateString(),
            'gestational_week' => $this->gestational_week,
            'gestational_day' => $this->gestational_day,
            'bpd_mm' => $this->bpd_mm,
            'hc_mm' => $this->hc_mm,
            'ac_mm' => $this->ac_mm,
            'fl_mm' => $this->fl_mm,
            'efw_grams' => $this->efw_grams,
            'fetal_heart_rate' => $this->fetal_heart_rate,
            'placenta_location' => $this->placenta_location,
            'amniotic_fluid' => $this->amniotic_fluid,
            'image_name' => $this->image_original_filename,
            'image_url' => $this->image_path
                ? URL::temporarySignedRoute('gynecology.ultrasound-exams.file', now()->addMinutes(60), ['record' => $this->id, 'field' => 'image'])
                : null,
            'notes' => $this->notes,
        ];
    }
}
