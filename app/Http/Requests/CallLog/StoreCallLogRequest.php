<?php

namespace App\Http\Requests\CallLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallLogRequest extends FormRequest
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
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
