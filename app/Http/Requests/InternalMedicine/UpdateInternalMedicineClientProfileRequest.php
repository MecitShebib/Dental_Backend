<?php

namespace App\Http\Requests\InternalMedicine;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternalMedicineClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chronic_conditions' => ['nullable', 'array'],
            'chronic_conditions.*' => ['string', Rule::in(['diabetes_t1', 'diabetes_t2', 'hypertension', 'copd', 'asthma', 'heart_failure', 'coronary_artery_disease', 'hypothyroidism', 'hyperthyroidism', 'chronic_kidney_disease', 'liver_disease', 'other'])],
            'current_medications' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', '0'])],
            'rh' => ['nullable', Rule::in(['positive', 'negative'])],
            'smoking' => ['nullable', Rule::in(['never', 'former', 'current'])],
            'alcohol' => ['nullable', Rule::in(['none', 'occasional', 'regular'])],
            'height_cm' => ['nullable', 'numeric', 'min:30', 'max:260'],
            'family_history' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
