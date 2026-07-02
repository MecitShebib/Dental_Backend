<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\MobileOtpService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mobile = app(MobileOtpService::class)->normalizeMobile((string) $this->input('mobile'));

            $exists = User::query()
                ->where(function ($query) use ($mobile) {
                    $query->where('phone', $mobile)
                        ->orWhere('phone', '+'.$mobile)
                        ->orWhere('phone', $this->input('mobile'));
                })
                ->exists();

            if (! $exists) {
                $validator->errors()->add('mobile', 'The selected mobile is invalid.');
            }
        });
    }
}
