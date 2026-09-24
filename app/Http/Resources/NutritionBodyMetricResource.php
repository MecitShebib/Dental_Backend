<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class NutritionBodyMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'recorded_at' => $this->recorded_at?->toDateString(),
            'source' => $this->source,
            'weight_kg' => $this->weight_kg,
            'bmi' => $this->bmi,
            'body_fat_percent' => $this->body_fat_percent,
            'muscle_mass_kg' => $this->muscle_mass_kg,
            'visceral_fat_rating' => $this->visceral_fat_rating,
            'water_percent' => $this->water_percent,
            'bone_mass_kg' => $this->bone_mass_kg,
            'basal_metabolic_rate' => $this->basal_metabolic_rate,
            'waist_cm' => $this->waist_cm,
            'hip_cm' => $this->hip_cm,
            'right_arm_muscle_kg' => $this->right_arm_muscle_kg,
            'left_arm_muscle_kg' => $this->left_arm_muscle_kg,
            'right_arm_fat_percent' => $this->right_arm_fat_percent,
            'left_arm_fat_percent' => $this->left_arm_fat_percent,
            'right_leg_muscle_kg' => $this->right_leg_muscle_kg,
            'left_leg_muscle_kg' => $this->left_leg_muscle_kg,
            'right_leg_fat_percent' => $this->right_leg_fat_percent,
            'left_leg_fat_percent' => $this->left_leg_fat_percent,
            'trunk_muscle_kg' => $this->trunk_muscle_kg,
            'trunk_fat_percent' => $this->trunk_fat_percent,
            'metabolic_age' => $this->metabolic_age,
            'daily_calorie_need' => $this->daily_calorie_need,
            'notes' => $this->notes,
            'report_original_filename' => $this->report_original_filename,
            'report_url' => $this->report_path
                ? URL::temporarySignedRoute('nutrition.body-metrics.file', now()->addMinutes(60), ['bodyMetric' => $this->id])
                : null,
        ];
    }
}
