<?php

namespace App\Http\Requests\Gynecology;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPrenatalPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'last_menstrual_period' => ['required', 'date', 'before:today'],
            'preferred_start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
