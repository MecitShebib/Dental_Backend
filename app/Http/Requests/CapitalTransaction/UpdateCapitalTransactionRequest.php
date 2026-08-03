<?php

namespace App\Http\Requests\CapitalTransaction;

use App\Enums\CapitalTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCapitalTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(CapitalTransactionType::class)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }
}
