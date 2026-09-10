<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AiTreatmentPlanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiTreatmentPlanServiceSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_session_slot_finds_same_day_when_free(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        $monday = Carbon::now()->next(Carbon::MONDAY);

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $monday, 30);

        $this->assertSame($monday->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }

    public function test_resolve_session_slot_rolls_forward_when_day_is_fully_booked(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        $firstMonday = Carbon::now()->next(Carbon::MONDAY);
        $secondMonday = $firstMonday->copy()->addWeek();

        Appointment::create([
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'status' => 'scheduled',
            'date' => $firstMonday->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
        ]);

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $firstMonday, 30);

        $this->assertSame($secondMonday->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    public function test_resolve_session_slot_skips_a_time_that_has_already_passed_today(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();

        // The schedule grid itself has no notion of "now" -- 09:00 is
        // technically free all day, but it's 11:15 right now, so the plan
        // must not schedule a session earlier than the current moment.
        $this->travelTo(Carbon::today()->setTime(11, 15));

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, Carbon::today(), 30);

        $this->assertSame(Carbon::today()->toDateString(), $slot['date']);
        $this->assertSame('11:30', $slot['start_time']);
    }

    public function test_resolve_session_slot_rolls_to_tomorrow_when_todays_schedule_has_already_ended(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();

        $this->travelTo(Carbon::today()->setTime(18, 0));

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, Carbon::today(), 30);

        $this->assertSame(Carbon::tomorrow()->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }

    public function test_resolve_session_slot_throws_when_nothing_found_within_the_search_window(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        // A doctor who only works Mondays and only has one 30-minute slot: booking the
        // next 2 Mondays exhausts a 14-day search window.
        $firstMonday = Carbon::now()->next(Carbon::MONDAY);
        foreach ([0, 1] as $weeksToAdd) {
            Appointment::create([
                'doctor_id' => $doctor->id,
                'type' => 'unavailable',
                'status' => 'scheduled',
                'date' => $firstMonday->copy()->addWeeks($weeksToAdd)->toDateString(),
                'start_time' => '09:00',
                'duration_minutes' => 30,
                'end_time' => '09:30',
            ]);
        }

        $this->expectException(ValidationException::class);

        app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $firstMonday, 30, 14);
    }
}
