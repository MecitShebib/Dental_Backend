<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentActionStateTest extends TestCase
{
    use RefreshDatabase;

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '00:00:00',
            'end_time' => '23:30:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function makeClient(string $code): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Action State Client',
            'phone' => '+9639000'.substr($code, -4),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    /**
     * A scheduled appointment whose end time has long passed, with nobody
     * ever marking attendance/no-show, must stay actionable indefinitely --
     * the front desk needs to be able to correct a forgotten check-in days
     * later, not just within the appointment's own time window.
     */
    public function test_a_scheduled_appointment_stays_checkin_actionable_long_after_its_time_has_passed(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9001');

        $appointmentId = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->subDays(3)->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.action_state', 'checkin')
            ->assertJsonPath('data.is_past', true);

        // Still actually checkable-in, not just cosmetically -- the backend
        // guard only cares about status=scheduled + no existing visit.
        $this->postJson("/api/appointments/{$appointmentId}/check-in", [])
            ->assertOk();
    }

    public function test_a_far_future_appointment_is_manage_not_checkin(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9002');

        $appointmentId = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.action_state', 'manage')
            ->assertJsonPath('data.is_future', true);
    }

    public function test_a_completed_appointment_is_locked(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9003');

        $appointmentId = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/appointments/{$appointmentId}/check-in", [])->assertOk();

        $this->getJson("/api/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.action_state', 'locked');
    }
}
