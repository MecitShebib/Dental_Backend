<?php

namespace App\Http\Requests\LabResult;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'test_name' => ['required', 'string', 'max:255'],
            'result_value' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:100'],
            'is_abnormal' => ['nullable', 'boolean'],
            'test_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
