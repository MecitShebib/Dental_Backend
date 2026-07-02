<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'payment_date' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
