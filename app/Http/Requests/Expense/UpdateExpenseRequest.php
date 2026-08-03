<?php

namespace App\Http\Requests\Expense;

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
        ];
    }
}
