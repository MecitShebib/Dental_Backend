<?php

namespace App\Http\Requests\GeneralSurgery;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPerioperativeCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'operation' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'preferred_start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
