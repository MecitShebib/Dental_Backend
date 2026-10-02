<?php

namespace App\Http\Requests\GeneralPractice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralPracticeClientProfileRequest extends FormRequest
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
            'adult_vaccination_status' => ['nullable', 'array:tetanus,influenza,pneumococcal,hepatitis_b,covid19'],
            'adult_vaccination_status.tetanus' => ['nullable', 'date'],
            'adult_vaccination_status.influenza' => ['nullable', 'date'],
            'adult_vaccination_status.pneumococcal' => ['nullable', 'date'],
            'adult_vaccination_status.hepatitis_b' => ['nullable', 'date'],
            'adult_vaccination_status.covid19' => ['nullable', 'date'],
        ];
    }
}
