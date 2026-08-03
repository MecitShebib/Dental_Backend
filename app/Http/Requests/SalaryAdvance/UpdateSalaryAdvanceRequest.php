<?php

namespace App\Http\Requests\SalaryAdvance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'advance_date' => ['sometimes', 'required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
