<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentConflictService
{
    public function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        return $this->parseTime($startTime)
            ->addMinutes($durationMinutes)
            ->format('H:i:s');
    }

    public function assertWithinSchedule(User $doctor, string $date, string $startTime, int $durationMinutes): void
    {
        $schedule = $doctor->doctorSchedule()->with('workingDays')->first();

        if (! $schedule) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor does not have a working schedule.'],
            ]);
        }

        $weekday = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $worksThatDay = $schedule->workingDays->contains(fn ($day) => $day->weekday->value === $weekday);

        if (! $worksThatDay) {
            throw ValidationException::withMessages([
                'date' => ['The selected doctor is not working on this day.'],
            ]);
        }

        $start = $this->parseTime($startTime);
        $end = $start->copy()->addMinutes($durationMinutes);
        $dayStart = $this->parseTime($schedule->start_time);
        $dayEnd = $this->parseTime($schedule->end_time);

        if ($start->lt($dayStart) || $end->gt($dayEnd)) {
            throw ValidationException::withMessages([
                'start_time' => ['The appointment must be within the doctor working hours.'],
            ]);
        }
    }

    public function assertNoConflict(int $doctorId, string $date, string $startTime, int $durationMinutes, ?int $ignoreAppointmentId = null): void
    {
        $newStart = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($startTime, 0, 5));
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);

        $appointments = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->get();

        foreach ($appointments as $appointment) {
            $existingStart = Carbon::parse($appointment->date->format('Y-m-d').' '.$appointment->start_time);
            $existingEnd = $existingStart->copy()->addMinutes($appointment->duration_minutes);

            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                throw ValidationException::withMessages([
                    'appointment' => ['The selected doctor already has a booked or unavailable slot that overlaps with this time range.'],
                ]);
            }
        }
    }

    protected function parseTime(string $time): Carbon
    {
        return Carbon::parse('2000-01-01 '.substr($time, 0, 5));
    }
}
