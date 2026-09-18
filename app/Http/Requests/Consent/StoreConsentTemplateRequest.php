<?php

namespace App\Http\Requests\Consent;

use App\Enums\ClientLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*.heading' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string'],
            'language' => ['required', Rule::enum(ClientLanguage::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
