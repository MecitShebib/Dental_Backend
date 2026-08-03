<?php

namespace App\Http\Requests\CapitalTransaction;

use App\Enums\CapitalTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCapitalTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CapitalTransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }
}
