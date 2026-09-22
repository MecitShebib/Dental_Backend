<?php

namespace App\Http\Requests\CariTransaction;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCariTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partyable_type' => ['required', Rule::in(['cari_party', 'user', 'lab_partner'])],
            'partyable_id' => ['required', 'integer'],
            'invoice_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            // required_without only guarantees "at least one" -- CariTransaction's
            // own docblock says a row is never both a debit and a credit at
            // once. Plain `prohibits` doesn't fit here: the Cari page's form
            // always submits both keys (whichever the user didn't fill in is
            // sent as 0, not omitted), so this checks the *values* -- not
            // presence -- are mutually exclusive.
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
