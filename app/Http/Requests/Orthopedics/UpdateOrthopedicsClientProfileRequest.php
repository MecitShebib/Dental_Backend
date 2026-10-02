<?php

namespace App\Http\Requests\Orthopedics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrthopedicsClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'affected_region' => ['nullable', Rule::in(['shoulder', 'elbow', 'wrist_hand', 'hip', 'knee', 'ankle_foot', 'cervical_spine', 'thoracic_spine', 'lumbar_spine', 'other'])],
            'side' => ['nullable', Rule::in(['right', 'left', 'bilateral'])],
            'complaint_onset_date' => ['nullable', 'date'],
            'injury_mechanism' => ['nullable', 'string', 'max:5000'],
            'previous_surgeries_implants' => ['nullable', 'string', 'max:5000'],
            'cast_splint_status' => ['nullable', Rule::in(['none', 'cast', 'splint', 'brace'])],
            'occupation_sport' => ['nullable', 'string', 'max:5000'],
            'dominant_hand' => ['nullable', Rule::in(['right', 'left', 'ambidextrous'])],
        ];
    }
}
