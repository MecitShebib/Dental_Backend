<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            // Positive magnitude for in/out; adjustment accepts a signed delta.
            'quantity' => [
                'required',
                'numeric',
                Rule::when(in_array($this->input('type'), ['in', 'out'], true), ['min:0.01'], ['not_in:0']),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
            'occurred_on' => ['required', 'date'],
        ];
    }
}
