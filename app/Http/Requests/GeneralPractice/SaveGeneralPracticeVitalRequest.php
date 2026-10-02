<?php

namespace App\Http\Requests\GeneralPractice;

use Illuminate\Foundation\Http\FormRequest;

class SaveGeneralPracticeVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'systolic' => ['nullable', 'integer', 'min:40', 'max:300'],
            'diastolic' => ['nullable', 'integer', 'min:20', 'max:200'],
            'pulse' => ['nullable', 'integer', 'min:20', 'max:250'],
            'temperature_c' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'spo2' => ['nullable', 'integer', 'min:50', 'max:100'],
            'blood_glucose' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
