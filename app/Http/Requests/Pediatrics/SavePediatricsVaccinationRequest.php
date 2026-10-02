<?php

namespace App\Http\Requests\Pediatrics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePediatricsVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'administered_at' => ['required', 'date'],
            'vaccine' => ['required', Rule::in(['hep_b', 'bcg', 'dabt_ipa_hib', 'kpa', 'opa', 'kkk', 'su_cicegi', 'hep_a', 'td', 'other'])],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
