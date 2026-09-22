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
            // Scoped to the acting company: a plain exists:clients,id runs
            // straight against the table and so bypasses Client's
            // BelongsToCompany global scope, letting another company's
            // client_id validate (and then blow up as a TypeError downstream,
            // after the broken row was already written).
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id)->whereNull('deleted_at')), Rule::requiredIf(fn () => $this->input('type') === AppointmentType::Booked->value)],
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', Rule::in([30, 60, 90])],
            'notes' => ['nullable', 'string'],
            'planned_summary' => ['nullable', 'string'],
            'charge_items' => ['nullable', 'array'],
            'charge_items.*.description' => ['nullable', 'string', 'max:255'],
            'charge_items.*.amount' => ['required', 'numeric'],
            'charge_items.*.treatment_catalog_id' => ['nullable', 'integer', Rule::exists('treatment_catalog', 'id')->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))],
        ];
    }
}
