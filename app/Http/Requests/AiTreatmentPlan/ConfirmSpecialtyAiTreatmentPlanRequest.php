<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by all 4 non-dental specialty AI-plan confirm endpoints. Same shape
 * as dental's ConfirmAiTreatmentPlanRequest minus `odontogram_v2_status`
 * (there's no odontogram equivalent for these specialties -- see
 * SpecialtyAiTreatmentPlanService's docblock).
 */
class ConfirmSpecialtyAiTreatmentPlanRequest extends FormRequest
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
            'sessions.*.charge_items' => ['nullable', 'array'],
            'sessions.*.charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'sessions.*.charge_items.*.amount' => ['required', 'numeric'],
            'sessions.*.charge_items.*.treatment_catalog_id' => ['nullable', 'integer', Rule::exists('treatment_catalog', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))],
        ];
    }
}
