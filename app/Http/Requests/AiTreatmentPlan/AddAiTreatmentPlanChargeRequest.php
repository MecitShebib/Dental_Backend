<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;

class AddAiTreatmentPlanChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'charge_items' => ['required', 'array', 'min:1'],
            'charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'charge_items.*.amount' => ['required', 'numeric'],
        ];
    }
}
