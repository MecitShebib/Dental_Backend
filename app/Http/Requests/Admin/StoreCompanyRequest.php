<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Rules\ValidPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    use ScopesErrorsToModal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:companies,code'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:100', new ValidPhone],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
