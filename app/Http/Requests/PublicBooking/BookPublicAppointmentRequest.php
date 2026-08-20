<?php

namespace App\Http\Requests\PublicBooking;

use Illuminate\Foundation\Http\FormRequest;

class BookPublicAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:50'],
            'client_email' => ['nullable', 'email', 'max:255'],
            // Honeypot: a real visitor never sees or fills this field (hidden
            // by CSS on the booking page). Any value means it's a bot.
            'website' => ['prohibited'],
        ];
    }
}
