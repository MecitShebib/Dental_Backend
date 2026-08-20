<?php

namespace App\Http\Requests\Expense;

use App\Enums\CariCurrency;
use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            // Optional cari hesap counterparty this expense is billed against
            // (a supplier/contracted institution/etc., or a reused doctor/lab
            // record) -- see CariLedgerService::resolvePartyable().
            'cari_partyable_type' => ['nullable', 'string', Rule::in(['cari_party', 'user', 'lab_partner'])],
            'cari_partyable_id' => ['required_with:cari_partyable_type', 'nullable', 'integer'],
            'cari_currency' => ['nullable', Rule::enum(CariCurrency::class)],
            'cari_exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
