<?php

namespace App\Http\Requests\DoctorSchedule;

use App\Enums\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', Rule::in([30, 60, 90])],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', Rule::enum(Weekday::class)],
        ];
    }
}
