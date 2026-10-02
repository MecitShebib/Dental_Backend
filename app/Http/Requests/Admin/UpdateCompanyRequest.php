<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClientLanguage;
use App\Enums\CompanyCurrency;
use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Rules\ValidPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    use ScopesErrorsToModal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('companies', 'code')->ignore($companyId)],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:100', new ValidPhone],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'currency' => ['required', Rule::enum(CompanyCurrency::class)],
            'language' => ['required', Rule::enum(ClientLanguage::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
