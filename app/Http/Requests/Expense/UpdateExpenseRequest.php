<?php

namespace App\Http\Requests\Expense;

use App\Enums\CariCurrency;
use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'required', Rule::enum(ExpenseCategory::class)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cari_partyable_type' => ['nullable', 'string', Rule::in(['cari_party', 'user', 'lab_partner'])],
            'cari_partyable_id' => ['required_with:cari_partyable_type', 'nullable', 'integer'],
            'cari_currency' => ['nullable', Rule::enum(CariCurrency::class)],
            'cari_exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
