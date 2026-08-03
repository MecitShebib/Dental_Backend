<?php

namespace App\Http\Requests\LabCase;

use App\Enums\LabCaseWorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer'],
            'lab_partner_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'work_type' => ['required', Rule::enum(LabCaseWorkType::class)],
            'teeth' => ['nullable', 'array'],
            'teeth.*' => ['string'],
            'material' => ['nullable', 'string', 'max:255'],
            'shade' => ['nullable', 'string', 'max:50'],
            'sent_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'lab_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
