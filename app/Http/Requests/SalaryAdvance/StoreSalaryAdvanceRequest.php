<?php

namespace App\Http\Requests\SalaryAdvance;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'advance_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
