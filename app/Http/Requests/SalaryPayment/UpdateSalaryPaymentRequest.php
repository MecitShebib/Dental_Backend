<?php

namespace App\Http\Requests\SalaryPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
        ];
    }
}
