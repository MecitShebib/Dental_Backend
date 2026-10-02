<?php

namespace App\Http\Requests\Pediatrics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePediatricsClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gestational_age_weeks_at_birth' => ['nullable', 'integer', 'min:20', 'max:45'],
            'birth_weight_g' => ['nullable', 'integer', 'min:200', 'max:7000'],
            'birth_length_cm' => ['nullable', 'numeric', 'min:20', 'max:70'],
            'birth_head_circumference_cm' => ['nullable', 'numeric', 'min:15', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:255'],
            'guardian_relation' => ['nullable', Rule::in(['mother', 'father', 'other'])],
            'feeding_type' => ['nullable', Rule::in(['breast', 'formula', 'mixed', 'solid'])],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', '0'])],
            'rh' => ['nullable', Rule::in(['positive', 'negative'])],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'chronic_conditions' => ['nullable', 'string', 'max:5000'],
            'developmental_milestones' => ['nullable', 'array', 'max:50'],
            'developmental_milestones.*.milestone' => ['nullable', 'string', 'max:255'],
            'developmental_milestones.*.achieved_at_months' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }
}
