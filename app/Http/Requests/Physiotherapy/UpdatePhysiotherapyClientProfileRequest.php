<?php

namespace App\Http\Requests\Physiotherapy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePhysiotherapyClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'referring_physician' => ['nullable', 'string', 'max:255'],
            'affected_region' => ['nullable', Rule::in(['shoulder', 'elbow', 'wrist_hand', 'hip', 'knee', 'ankle_foot', 'cervical_spine', 'thoracic_spine', 'lumbar_spine', 'other'])],
            'side' => ['nullable', Rule::in(['right', 'left', 'bilateral'])],
            'prescribed_session_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'home_exercise_program' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
