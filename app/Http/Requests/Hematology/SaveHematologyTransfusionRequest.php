<?php

namespace App\Http\Requests\Hematology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHematologyTransfusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transfused_at' => ['required', 'date'],
            'product' => ['required', Rule::in(['prbc', 'ffp', 'platelet', 'cryo', 'whole_blood'])],
            'units' => ['nullable', 'integer', 'min:1', 'max:50'],
            'reaction' => ['nullable', 'boolean'],
            'reaction_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
