<?php

namespace App\Http\Requests\InternalMedicine;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmChronicCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'condition' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'preferred_start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
