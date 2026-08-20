<?php

namespace App\Http\Requests\MessageTemplate;

use App\Enums\ClientLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', Rule::in(['appointment_reminder', 'patient_recall', 'booking_confirmation', 'satisfaction_survey'])],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'language' => ['required', Rule::enum(ClientLanguage::class)],
            // Null/empty clears the override and reverts to the built-in default.
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
