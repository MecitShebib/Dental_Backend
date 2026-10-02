<?php

namespace App\Http\Requests\PublicBooking;

use Illuminate\Foundation\Http\FormRequest;

class RequestBookingOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // MOBILE_OTP_CHANNEL decides which contact the code goes to, so that
        // one is required and the other optional.
        $emailChannel = config('services.otp.channel') === 'email';

        return [
            'client_phone' => [$emailChannel ? 'nullable' : 'required', 'string', 'max:50'],
            'client_email' => [$emailChannel ? 'required' : 'nullable', 'email', 'max:255'],
        ];
    }
}
