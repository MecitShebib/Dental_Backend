<?php

namespace App\Http\Requests\GeneralPractice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGeneralPracticeReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referred_at' => ['required', 'date'],
            'target_specialty' => ['required', 'string', 'max:255'],
            'target_institution' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['pending', 'completed'])],
        ];
    }
}
