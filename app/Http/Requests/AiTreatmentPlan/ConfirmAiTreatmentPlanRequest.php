<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmAiTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer'],
            'sessions' => ['required', 'array', 'min:1', 'max:8'],
            'sessions.*.date' => ['required', 'date'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.duration_minutes' => ['required', 'integer', Rule::in([30, 60, 90])],
            'sessions.*.session_description' => ['required', 'string'],
            'sessions.*.odontogram_v2_status' => ['required', 'string', 'json'],
            'sessions.*.charge_items' => ['nullable', 'array'],
            'sessions.*.charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'sessions.*.charge_items.*.amount' => ['required', 'numeric'],
        ];
    }
}
