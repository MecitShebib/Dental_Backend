<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            // Which specialties this branch operates -- omitted/empty means
            // unrestricted (offered from every specialty).
            'specialty_ids' => ['nullable', 'array'],
            'specialty_ids.*' => ['integer', Rule::exists('specialties', 'id')],
        ];
    }
}
