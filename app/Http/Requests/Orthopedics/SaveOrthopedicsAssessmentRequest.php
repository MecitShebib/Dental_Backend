<?php

namespace App\Http\Requests\Orthopedics;

use Illuminate\Foundation\Http\FormRequest;

class SaveOrthopedicsAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assessed_at' => ['required', 'date'],
            'pain_vas' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rom_measurements' => ['nullable', 'array', 'max:50'],
            'rom_measurements.*.joint' => ['nullable', 'string', 'max:255'],
            'rom_measurements.*.movement' => ['nullable', 'string', 'max:255'],
            'rom_measurements.*.degrees' => ['nullable', 'integer', 'min:0', 'max:360'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
