<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmNutritionCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'treatment_code' => ['required', 'string', 'in:followup_session,body_composition_analysis'],
            'session_count' => ['required', 'integer', 'min:1', 'max:12'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:90'],
            'start_date' => ['required', 'date'],
            'preferred_start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
