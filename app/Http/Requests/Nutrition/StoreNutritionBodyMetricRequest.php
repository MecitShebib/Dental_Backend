<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;

class StoreNutritionBodyMetricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recorded_at' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'body_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'muscle_mass_kg' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'visceral_fat_rating' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'water_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'bone_mass_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'basal_metabolic_rate' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'waist_cm' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'hip_cm' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'right_arm_muscle_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'left_arm_muscle_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'right_arm_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'left_arm_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'right_leg_muscle_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'left_leg_muscle_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'right_leg_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'left_leg_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'trunk_muscle_kg' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'trunk_fat_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'metabolic_age' => ['nullable', 'integer', 'min:0', 'max:255'],
            'daily_calorie_need' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'report' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            // Set by the frontend when this save follows the AI-extraction
            // path (BodyMetricController::extract()) -- defaults to manual.
            'source' => ['nullable', 'in:manual,device_import'],
        ];
    }
}
