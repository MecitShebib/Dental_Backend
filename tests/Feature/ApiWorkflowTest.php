<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_overlapping_appointments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'saturday']);

        $client = Client::create([
            'client_code' => 'CL-1001',
            'name' => 'Mohammad',
            'phone' => '+963900001001',
            'gender' => 'male',
            'status' => 'new',
        ]);

        $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => '2026-05-09',
            'start_time' => '11:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'date' => '2026-05-09',
            'start_time' => '10:00',
            'duration_minutes' => 90,
        ])->assertStatus(422)->assertJsonValidationErrors('appointment');
    }

    public function test_check_in_creates_visit_and_updates_appointment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'saturday']);

        $client = Client::create([
            'client_code' => 'CL-1002',
            'name' => 'Layan',
            'phone' => '+963900001002',
            'gender' => 'female',
            'status' => 'new',
        ]);

        $appointmentId = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => '2026-05-09',
            'start_time' => '09:00',
            'duration_minutes' => 30,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/appointments/{$appointmentId}/check-in", [
            'summary' => 'Started treatment',
            'notes' => 'Visit notes',
        ])->assertOk()->assertJsonPath('data.attendance_status', 'attended');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointmentId,
            'attendance_status' => 'attended',
        ]);
    }
}
