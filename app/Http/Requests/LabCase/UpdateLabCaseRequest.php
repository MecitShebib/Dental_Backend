<?php

namespace App\Http\Requests\LabCase;

use App\Enums\LabCaseStatus;
use App\Enums\LabCaseWorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['sometimes', 'required', 'integer'],
            'lab_partner_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'work_type' => ['sometimes', 'required', Rule::enum(LabCaseWorkType::class)],
            'teeth' => ['nullable', 'array'],
            'teeth.*' => ['string'],
            'material' => ['nullable', 'string', 'max:255'],
            'shade' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'required', Rule::enum(LabCaseStatus::class)],
            'sent_date' => ['sometimes', 'required', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'lab_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
