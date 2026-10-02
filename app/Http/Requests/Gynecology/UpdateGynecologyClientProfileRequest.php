<?php

namespace App\Http\Requests\Gynecology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGynecologyClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_menstrual_period' => ['nullable', 'date'],
            'gravida' => ['nullable', 'integer', 'min:0', 'max:30'],
            'para' => ['nullable', 'integer', 'min:0', 'max:30'],
            'abortus' => ['nullable', 'integer', 'min:0', 'max:30'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', '0'])],
            'rh' => ['nullable', Rule::in(['positive', 'negative'])],
            'cycle_length_days' => ['nullable', 'integer', 'min:10', 'max:120'],
            'cycle_regularity' => ['nullable', Rule::in(['regular', 'irregular'])],
            'contraception_method' => ['nullable', Rule::in(['none', 'oral', 'iud', 'condom', 'implant', 'injection', 'other'])],
            'last_pap_smear_date' => ['nullable', 'date'],
            'menopause_status' => ['nullable', Rule::in(['pre', 'peri', 'post'])],
            'previous_delivery_types' => ['nullable', 'array:normal,cesarean'],
            'previous_delivery_types.normal' => ['nullable', 'integer', 'min:0', 'max:20'],
            'previous_delivery_types.cesarean' => ['nullable', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
