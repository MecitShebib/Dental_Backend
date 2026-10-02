<?php

namespace App\Http\Requests\GeneralSurgery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGeneralSurgeryFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'followup_date' => ['required', 'date'],
            'operation_id' => ['nullable', 'integer'],
            'wound_status' => ['nullable', Rule::in(['clean', 'serous', 'infected', 'dehiscence'])],
            'drain_removed_at' => ['nullable', 'date'],
            'sutures_removed_at' => ['nullable', 'date'],
            'complications' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
