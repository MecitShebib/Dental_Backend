<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdjustTreatmentProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => [
                'required',
                'numeric',
                Rule::when($this->input('type') === 'percentage', ['gt:-100']),
            ],
            // Restricts the adjustment to one specialty's own catalog rows
            // (SettingsPage.jsx's activeSpecialtyId) -- omitted/null means every
            // row in the company's catalog, same as the "no specialty selected"
            // view of the Pricing list itself.
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
        ];
    }
}
