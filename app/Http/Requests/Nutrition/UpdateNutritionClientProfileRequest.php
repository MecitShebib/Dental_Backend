<?php

namespace App\Http\Requests\Nutrition;

use App\Enums\NutritionActivityLevel;
use App\Enums\NutritionBodyType;
use App\Enums\NutritionDietaryType;
use App\Enums\NutritionGoal;
use App\Enums\NutritionSubstanceUseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNutritionClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'body_type' => ['nullable', Rule::enum(NutritionBodyType::class)],
            'dietary_type' => ['nullable', Rule::enum(NutritionDietaryType::class)],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
            'chronic_conditions' => ['nullable', 'array'],
            'chronic_conditions.*' => ['string', 'max:255'],
            'medications_affecting_diet' => ['nullable', 'string'],
            'smoking_status' => ['nullable', Rule::enum(NutritionSubstanceUseStatus::class)],
            'alcohol_status' => ['nullable', Rule::enum(NutritionSubstanceUseStatus::class)],
            'activity_level' => ['nullable', Rule::enum(NutritionActivityLevel::class)],
            'goal' => ['nullable', Rule::enum(NutritionGoal::class)],
            'target_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
