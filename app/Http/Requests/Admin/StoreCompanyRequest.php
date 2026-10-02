<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClientLanguage;
use App\Enums\CompanyCurrency;
use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Rules\ValidPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Mandatory at creation -- the whole app (pricing, accounting,
            // everything formatCurrency touches) renders in this one
            // currency from then on, see Company::$casts.
            'currency' => ['required', Rule::enum(CompanyCurrency::class)],
            // Titles of the auto-seeded System Messages are written in this
            // language (see SystemMessageService::titleFor()).
            'language' => ['required', Rule::enum(ClientLanguage::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
