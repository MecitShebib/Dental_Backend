<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmThenCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_plan_then_checking_in_carries_the_real_planned_data_into_the_visit(): void
    {
        Storage::fake('public');

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
                'image' => UploadedFile::fake()->create('session-1.png', 10, 'image/png'),
            ]],
        ], ['Accept' => 'application/json'])->assertCreated();

        $appointmentId = $confirmResponse->json('data.0.id');
        $plannedImagePath = $confirmResponse->json('data.0.planned_image_url');
        $this->assertNotNull($plannedImagePath);

        $checkInResponse = $this->postJson("/api/appointments/{$appointmentId}/check-in", [])
            ->assertOk();

        $this->assertStringContainsString('endo-filling-incomplete', $checkInResponse->json('data.summary'));
        $this->assertSame('Open the canal and clean it.', $checkInResponse->json('data.notes'));
        $this->assertNotNull($checkInResponse->json('data.odontogram_image_url'));

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointmentId,
            'notes' => 'Open the canal and clean it.',
        ]);
    }
}
