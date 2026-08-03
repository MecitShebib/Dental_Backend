<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentChargeItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(string $code = 'CL-5301'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Yousef',
            'phone' => '+963900005301',
            'gender' => 'male',
            'status' => 'new',
        ]);
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

    public function test_creating_a_booked_appointment_stores_its_charge_items(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'charge_items' => [
                ['description' => 'Crown', 'amount' => 4500],
            ],
        ])->assertCreated();

        $appointmentId = $response->json('data.id');

        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'appointment',
            'source_id' => $appointmentId,
            'amount' => 4500,
            'description' => 'Crown',
        ]);
    }

    public function test_creating_an_unavailable_block_ignores_charge_items(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'charge_items' => [
                ['description' => 'Should be ignored', 'amount' => 999],
            ],
        ])->assertCreated();

        $this->assertDatabaseCount('treatment_charges', 0);
    }

    public function test_updating_an_appointments_charge_items_replaces_the_previous_set(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-5302');

        $created = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'duration_minutes' => 30,
            'charge_items' => [['description' => 'Initial item', 'amount' => 200]],
        ])->assertCreated();
        $appointmentId = $created->json('data.id');

        $this->putJson("/api/appointments/{$appointmentId}", [
            'doctor_id' => $doctor->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'duration_minutes' => 30,
            'charge_items' => [['description' => 'Replaced item', 'amount' => 350]],
        ])->assertOk();

        $this->assertDatabaseCount('treatment_charges', 1);
        $this->assertDatabaseHas('treatment_charges', [
            'source_type' => 'appointment',
            'source_id' => $appointmentId,
            'amount' => 350,
            'description' => 'Replaced item',
        ]);
    }

    public function test_deleting_an_appointment_removes_its_charge_items(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-5303');

        $created = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '12:00',
            'duration_minutes' => 30,
            'charge_items' => [['description' => 'Item', 'amount' => 200]],
        ])->assertCreated();
        $appointmentId = $created->json('data.id');

        $this->deleteJson("/api/appointments/{$appointmentId}")->assertOk();

        $this->assertDatabaseCount('treatment_charges', 0);
    }
}
