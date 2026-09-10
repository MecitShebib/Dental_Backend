<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\SpecialtyAiTreatmentPlanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors AiTreatmentPlanServiceSlotTest -- SpecialtyAiTreatmentPlanService
 * has its own copy of resolveSessionSlot() (see its class docblock for why
 * it's a separate class rather than a shared one), so the same "don't
 * schedule a time that's already passed today" fix needs its own coverage.
 */
class SpecialtyAiTreatmentPlanServiceSlotTest extends TestCase
{
    use RefreshDatabase;

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

        $this->travelTo(Carbon::today()->setTime(11, 15));

        $slot = app(SpecialtyAiTreatmentPlanService::class)->resolveSessionSlot($doctor, Carbon::today(), 30);

        $this->assertSame(Carbon::today()->toDateString(), $slot['date']);
        $this->assertSame('11:30', $slot['start_time']);
    }

    public function test_resolve_session_slot_rolls_to_tomorrow_when_todays_schedule_has_already_ended(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();

        $this->travelTo(Carbon::today()->setTime(18, 0));

        $slot = app(SpecialtyAiTreatmentPlanService::class)->resolveSessionSlot($doctor, Carbon::today(), 30);

        $this->assertSame(Carbon::tomorrow()->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }
}
