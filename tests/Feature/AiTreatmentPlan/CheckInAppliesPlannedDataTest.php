<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckInAppliesPlannedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function doctorForToday(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => strtolower(now()->format('l'))]);

        return $doctor;
    }

    protected function makeClient(string $code): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Nour',
            'phone' => '+963900004001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_check_in_defaults_visit_fields_from_the_appointments_ai_plan(): void
    {
        $doctor = $this->doctorForToday();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-4001');

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
            'planned_summary' => json_encode([
                '__visit_odontogram__' => true,
                'companyVersion' => 2,
                'odontogramV2Status' => ['teeth' => ['13' => ['endo' => 'endo-filling-incomplete']]],
            ]),
            'planned_notes' => 'Open the canal and clean it.',
            'planned_image_path' => 'odontogram-plans/example.png',
        ]);

        $this->postJson("/api/appointments/{$appointment->id}/check-in", [])->assertOk();

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointment->id,
            'notes' => 'Open the canal and clean it.',
            'odontogram_image_path' => 'odontogram-plans/example.png',
        ]);

        $visit = $appointment->visit()->firstOrFail();
        $this->assertStringContainsString('endo-filling-incomplete', $visit->summary);
    }

    public function test_check_in_lets_the_doctor_override_the_ai_plan(): void
    {
        $doctor = $this->doctorForToday();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-4002');

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
            'planned_notes' => 'AI suggested notes.',
        ]);

        $this->postJson("/api/appointments/{$appointment->id}/check-in", [
            'notes' => 'Doctor wrote something different.',
        ])->assertOk();

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointment->id,
            'notes' => 'Doctor wrote something different.',
        ]);
    }
}
