<?php

namespace App\Http\Requests\Orthopedics;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmRehabCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'injury' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'preferred_start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
