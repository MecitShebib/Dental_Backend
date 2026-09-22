<?php

namespace App\Http\Requests\CariTransaction;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCariTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            // See StoreCariTransactionRequest's notBothPositiveRule() -- same
            // reasoning, the edit form submits both keys the same way.
            'debit' => ['required_without:credit', 'nullable', 'numeric', 'min:0', $this->notBothPositiveRule()],
            'credit' => ['required_without:debit', 'nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(CariCurrency::class)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'transaction_type' => ['required', Rule::enum(CariTransactionType::class)],
            'expense_category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function notBothPositiveRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ((float) $value > 0 && (float) $this->input('credit') > 0) {
                $fail('A transaction cannot have both a debit and a credit amount.');
            }
        };
    }
}
