<?php

namespace App\Http\Requests\Appointment;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'doctor_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'type' => ['sometimes', 'required', Rule::enum(AppointmentType::class)],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'duration_minutes' => ['sometimes', 'required', 'integer', Rule::in([30, 60, 90])],
            'notes' => ['nullable', 'string'],
            'planned_summary' => ['nullable', 'string'],
            'planned_notes' => ['nullable', 'string'],
            'charge_items' => ['nullable', 'array'],
            'charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'charge_items.*.amount' => ['required', 'numeric'],
        ];
    }
}
