<?php

namespace App\Http\Requests\SalaryPayment;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'paid_at' => ['required', 'date'],
        ];
    }
}
