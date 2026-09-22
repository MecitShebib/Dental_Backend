<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmThenCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_plan_then_checking_in_carries_the_real_planned_data_into_the_visit(): void
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

        Sanctum::actingAs($doctor);

        $client = Client::create([
            'client_code' => 'CL-5001',
            'name' => 'Yousef',
            'phone' => '+963900005001',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $confirmResponse = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => $date,
                'start_time' => '09:00',
                'duration_minutes' => 30,
                'session_description' => 'Open the canal and clean it.',
                'odontogram_v2_status' => json_encode([
                    'version' => '1.3',
                    'globals' => [],
                    'teeth' => ['13' => ['endo' => 'endo-filling-incomplete']],
                ]),
            ]],
        ], ['Accept' => 'application/json'])->assertCreated();

        $appointmentId = $confirmResponse->json('data.0.id');

        $checkInResponse = $this->postJson("/api/appointments/{$appointmentId}/check-in", [])
            ->assertOk();

        $this->assertStringContainsString('endo-filling-incomplete', $checkInResponse->json('data.summary'));
        $this->assertSame('Open the canal and clean it.', $checkInResponse->json('data.notes'));

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointmentId,
            'notes' => 'Open the canal and clean it.',
        ]);
    }

    public function test_confirming_a_plan_with_a_session_today_marks_it_attended_immediately(): void
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

        Sanctum::actingAs($doctor);

        $client = Client::create([
            'client_code' => 'CL-5002',
            'name' => 'Lina',
            'phone' => '+963900005002',
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        $confirmResponse = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => Carbon::now()->toDateString(),
                'start_time' => '11:00',
                'duration_minutes' => 30,
                'session_description' => 'Filling.',
                'odontogram_v2_status' => json_encode([
                    'version' => '1.3',
                    'globals' => [],
                    'teeth' => ['14' => ['filling' => 'filling-composite']],
                ]),
                'charge_items' => [
                    ['description' => 'Composite filling', 'amount' => 500],
                ],
            ]],
        ], ['Accept' => 'application/json'])->assertCreated();

        $appointmentId = $confirmResponse->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointmentId,
            'attendance_status' => 'attended',
        ]);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'visit',
            'amount' => 500,
        ]);
    }
}
