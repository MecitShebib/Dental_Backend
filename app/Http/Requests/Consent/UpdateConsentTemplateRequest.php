<?php

namespace App\Http\Requests\Consent;

use App\Enums\ClientLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*.heading' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string'],
            'language' => ['nullable', Rule::enum(ClientLanguage::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
