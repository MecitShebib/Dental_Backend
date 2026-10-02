<?php

namespace App\Http\Requests\GeneralSurgery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSurgeryClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'indication' => ['nullable', 'string', 'max:5000'],
            'planned_operation' => ['nullable', 'string', 'max:255'],
            'asa_score' => ['nullable', Rule::in(['I', 'II', 'III', 'IV', 'V', 'VI'])],
            'previous_surgeries' => ['nullable', 'string', 'max:5000'],
            'anticoagulant_use' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', '0'])],
            'rh' => ['nullable', Rule::in(['positive', 'negative'])],
            'preop_checklist' => ['nullable', 'array'],
            'preop_checklist.*' => ['string', Rule::in(['cbc', 'coagulation', 'biochemistry', 'ecg', 'chest_xray', 'anesthesia_consult', 'informed_consent', 'fasting'])],
        ];
    }
}
