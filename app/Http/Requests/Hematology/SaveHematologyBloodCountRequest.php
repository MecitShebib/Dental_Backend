<?php

namespace App\Http\Requests\Hematology;

use Illuminate\Foundation\Http\FormRequest;

class SaveHematologyBloodCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'hb' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'hct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wbc' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'plt' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'mcv' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'ferritin' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'inr' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
