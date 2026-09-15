<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['sometimes', 'required', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'prescribed_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
            // The frontend always sends its full current item set (same
            // replace-all convention as TreatmentChargeService::syncItems) --
            // "sometimes" only covers a caller that isn't touching items at
            // all, never a partial list.
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.medication_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.dosage_instruction' => ['nullable', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string'],
        ];
    }
}
