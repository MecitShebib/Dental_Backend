<?php

namespace App\Http\Requests\Consent;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consent_template_id' => ['required', 'integer', 'exists:consent_templates,id'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            // "data:image/png;base64,...." from a <canvas> signature pad.
            'signature' => ['required', 'string'],
        ];
    }
}
