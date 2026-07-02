<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
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

    protected function makeClient(string $code = 'CL-3101'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Rama',
            'phone' => '+963900003101',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function sessionPayload(string $date): array
    {
        return [
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
        ];
    }

    public function test_it_creates_appointments_with_the_planned_data(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $response = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $appointmentId = $response->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'status' => 'scheduled',
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $this->assertSame('Open the canal and clean it.', $appointment->planned_notes);
        $this->assertStringContainsString('endo-filling-incomplete', $appointment->planned_summary);
        $this->assertNotNull($appointment->planned_image_path);
        Storage::disk('public')->assertExists($appointment->planned_image_path);

        $this->assertSame('Open the canal and clean it.', $response->json('data.0.planned_notes'));
        $this->assertNotNull($response->json('data.0.planned_image_url'));
    }

    public function test_it_rejects_the_whole_confirmation_if_any_session_conflicts(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3102');
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_it_creates_no_appointments_or_files_when_a_later_session_in_the_batch_conflicts(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3104');
        $firstDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $secondDate = Carbon::now()->next(Carbon::MONDAY)->addWeek()->toDateString();

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'date' => $secondDate,
            'start_time' => '09:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [
                $this->sessionPayload($firstDate),
                $this->sessionPayload($secondDate),
            ],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 1); // only the pre-existing unavailable block
        Storage::disk('public')->assertDirectoryEmpty('odontogram-plans');
    }

    public function test_it_creates_no_appointments_or_files_when_a_later_session_has_an_invalid_odontogram_shape(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3106');
        $firstDate = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $secondDate = Carbon::now()->next(Carbon::MONDAY)->addWeek()->toDateString();

        $validSession = $this->sessionPayload($firstDate);

        $invalidShapeSession = $this->sessionPayload($secondDate);
        // Valid JSON (passes the "json" validation rule) but decodes to a
        // string, not an object — must be rejected before any writes happen.
        $invalidShapeSession['odontogram_v2_status'] = json_encode('not-an-object');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$validSession, $invalidShapeSession],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 0);
        Storage::disk('public')->assertDirectoryEmpty('odontogram-plans');
    }

    public function test_it_rejects_a_plan_with_two_sessions_that_overlap_each_other(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3105');
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $sessionA = $this->sessionPayload($date);
        $sessionA['start_time'] = '09:00';
        $sessionA['duration_minutes'] = 30;

        $sessionB = $this->sessionPayload($date);
        $sessionB['start_time'] = '09:15';
        $sessionB['duration_minutes'] = 30;

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$sessionA, $sessionB],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 0);
        Storage::disk('public')->assertDirectoryEmpty('odontogram-plans');
    }

    public function test_it_validates_session_shape(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3103');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sessions');
    }
}
