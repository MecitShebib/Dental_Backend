<?php

namespace App\Http\Requests\GeneralSurgery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGeneralSurgeryOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operated_at' => ['required', 'date'],
            'operation_name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'anesthesia_type' => ['nullable', Rule::in(['general', 'spinal', 'epidural', 'local', 'sedation'])],
            'surgeon_notes' => ['nullable', 'string', 'max:5000'],
            'complications' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
