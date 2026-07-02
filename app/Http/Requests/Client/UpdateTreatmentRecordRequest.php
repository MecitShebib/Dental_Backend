<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTreatmentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'treatment_plan' => ['nullable', 'string'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
            'teeth' => ['nullable', 'array'],
            'teeth.*.tooth_number' => ['required', 'string', 'max:10'],
            'teeth.*.treatment_catalog_id' => ['required', 'integer', 'exists:treatment_catalog,id'],
            'teeth.*.unit_price' => ['required', 'numeric', 'min:0'],
            'teeth.*.notes' => ['nullable', 'string'],
        ];
    }
}
