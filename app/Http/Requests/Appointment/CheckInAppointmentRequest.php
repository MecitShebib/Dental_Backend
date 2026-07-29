<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class CheckInAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'create_payment_after_visit' => ['nullable', 'boolean'],
            'treatment_charge_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
