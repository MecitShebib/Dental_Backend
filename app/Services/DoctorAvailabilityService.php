<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DoctorAvailabilityService
{
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected AppointmentActionStateService $actionStates,
    ) {
    }

    public function availability(User $doctor, string $date): array
    {
        $schedule = $doctor->doctorSchedule()->with('workingDays')->first();
        $this->assertScheduleForDate($schedule, $date);

        $appointments = $this->appointmentsForDate($doctor, $date);
        $slotMinutes = $schedule->slot_minutes;
        $cursor = Carbon::parse('2000-01-01 '.substr($schedule->start_time, 0, 5));
        $end = Carbon::parse('2000-01-01 '.substr($schedule->end_time, 0, 5));
        $slots = [];

        while ($cursor->copy()->addMinutes($slotMinutes)->lte($end)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($slotMinutes);
            $appointment = $appointments->first(fn (Appointment $item) => $this->overlaps(
                $slotStart,
                $slotEnd,
                Carbon::parse('2000-01-01 '.substr($item->start_time, 0, 5)),
                Carbon::parse('2000-01-01 '.substr($item->end_time ?? $this->conflicts->calculateEndTime($item->start_time, $item->duration_minutes), 0, 5))
            ));

            $slots[] = [
                'time' => $slotStart->format('H:i'),
                'status' => $appointment ? 'filled' : 'free',
                'appointment' => $appointment,
            ];

            $cursor->addMinutes($slotMinutes);
        }

        return [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'slots' => $slots,
        ];
    }

    public function availableStartTimes(User $doctor, string $date, int $durationMinutes): array
    {
        $schedule = $doctor->doctorSchedule()->with('workingDays')->first();
        $this->assertScheduleForDate($schedule, $date);

        $times = [];
        $slotMinutes = $schedule->slot_minutes;
        $cursor = Carbon::parse('2000-01-01 '.substr($schedule->start_time, 0, 5));
        $end = Carbon::parse('2000-01-01 '.substr($schedule->end_time, 0, 5));

        while ($cursor->copy()->addMinutes($slotMinutes)->lte($end)) {
            $time = $cursor->format('H:i');

            try {
                $this->conflicts->assertWithinSchedule($doctor, $date, $time, $durationMinutes);
                $this->conflicts->assertNoConflict($doctor->id, $date, $time, $durationMinutes);
                $times[] = $time;
            } catch (ValidationException) {
            }

            $cursor->addMinutes($slotMinutes);
        }

        return [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'duration_minutes' => $durationMinutes,
            'start_times' => $times,
        ];
    }

    public function availableDurations(User $doctor, string $date, string $startTime): array
    {
        $durations = collect([30, 60, 90])->map(function (int $duration) use ($doctor, $date, $startTime) {
            try {
                $this->conflicts->assertWithinSchedule($doctor, $date, $startTime, $duration);
                $this->conflicts->assertNoConflict($doctor->id, $date, $startTime, $duration);

                return ['value' => $duration, 'available' => true];
            } catch (ValidationException) {
                return ['value' => $duration, 'available' => false];
            }
        })->all();

        return [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'start_time' => substr($startTime, 0, 5),
            'durations' => $durations,
        ];
    }

    protected function assertScheduleForDate($schedule, string $date): void
    {
        if (! $schedule) {
            throw ValidationException::withMessages([
                'doctor_id' => ['The selected doctor does not have a working schedule.'],
            ]);
        }

        $weekday = strtolower(Carbon::parse($date)->englishDayOfWeek);

        if (! $schedule->workingDays->contains(fn ($day) => $day->weekday->value === $weekday)) {
            throw ValidationException::withMessages([
                'date' => ['The selected doctor is not working on this day.'],
            ]);
        }
    }

    protected function appointmentsForDate(User $doctor, string $date): Collection
    {
        return $doctor->appointmentsAsDoctor()
            ->with(['client', 'doctor'])
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->get()
            ->each(function (Appointment $appointment): void {
                if (! $appointment->end_time) {
                    $appointment->end_time = $this->conflicts->calculateEndTime($appointment->start_time, $appointment->duration_minutes);
                }
            });
    }

    protected function overlaps(Carbon $startA, Carbon $endA, Carbon $startB, Carbon $endB): bool
    {
        return $startA->lt($endB) && $endA->gt($startB);
    }
}
