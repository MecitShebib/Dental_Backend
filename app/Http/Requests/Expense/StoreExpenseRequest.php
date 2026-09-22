<?php

namespace App\Http\Requests\Expense;

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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
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
            // No cari_currency/cari_exchange_rate here on purpose: an expense
            // amount is always in the company's base currency (it is what
            // leaves the TRY-only fund ledger), so the cari row it drives is
            // too -- see ExpenseController::syncCari().
        ];
    }
}
