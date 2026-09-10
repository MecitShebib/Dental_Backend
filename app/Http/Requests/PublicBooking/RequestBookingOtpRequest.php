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
        return [
            'client_phone' => ['required', 'string', 'max:50'],
        ];
    }
}
