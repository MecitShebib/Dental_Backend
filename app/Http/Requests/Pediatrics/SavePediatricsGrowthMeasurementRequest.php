<?php

namespace App\Http\Requests\Pediatrics;

use Illuminate\Foundation\Http\FormRequest;

class SavePediatricsGrowthMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:220'],
            'head_circumference_cm' => ['nullable', 'numeric', 'min:0', 'max:70'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
