<?php

namespace App\Http\Requests\CallLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:50'],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'status' => ['required', Rule::in(['answered', 'missed', 'voicemail'])],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'recording_url' => ['nullable', 'string', 'max:2048'],
            'occurred_at' => ['required', 'date'],
            'external_id' => ['required', 'string', 'max:255'],
        ];
    }
}
