<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentActionStateService
{
    public function for(Appointment $appointment): string
    {
        if ($appointment->status !== AppointmentStatus::Scheduled) {
            return 'locked';
        }

        $now = now();
        $start = $this->startDateTime($appointment);
        $end = $start->copy()->addMinutes($appointment->duration_minutes);

        if ($now->greaterThanOrEqualTo($end)) {
            return 'locked';
        }

        if ($now->greaterThanOrEqualTo($start->copy()->subHour())) {
            return 'checkin';
        }

        return 'manage';
    }

    public function metadata(Appointment $appointment): array
    {
        $now = now();
        $start = $this->startDateTime($appointment);

        return [
            'action_state' => $this->for($appointment),
            'is_past' => $now->greaterThanOrEqualTo($start),
            'is_future' => $now->lessThan($start),
            'is_within_one_hour' => $now->greaterThanOrEqualTo($start->copy()->subHour()) && $now->lessThan($start),
        ];
    }

    public function startDateTime(Appointment $appointment): Carbon
    {
        return Carbon::parse($appointment->date->format('Y-m-d').' '.$appointment->start_time);
    }
}
