<?php

namespace App\Http\Requests\Appointment;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id', Rule::requiredIf(fn () => $this->input('type') === AppointmentType::Booked->value)],
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', Rule::in([30, 60, 90])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
