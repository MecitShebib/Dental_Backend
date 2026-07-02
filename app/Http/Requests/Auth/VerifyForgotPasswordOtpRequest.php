<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyForgotPasswordOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string'],
            'otp' => ['required', 'string'],
            'otp_reference' => ['nullable', 'string'],
        ];
    }
}
