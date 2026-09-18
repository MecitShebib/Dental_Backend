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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'type' => ['required', Rule::enum(CapitalTransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }
}
