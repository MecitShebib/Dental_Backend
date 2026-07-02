<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreviewAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeOpenAiResponse(?array $sessions = null): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Pulp necrosis on tooth 13.',
                        'sessions' => $sessions ?? [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Open the canal and clean it.',
                                'teeth' => [
                                    [
                                        'tooth_number' => 13,
                                        'tooth_selection' => null,
                                        'crown_material' => null,
                                        'bridge_unit' => null,
                                        'endo' => 'endo-filling-incomplete',
                                        'filling_material' => null,
                                        'filling_surfaces' => [],
                                        'caries' => [],
                                        'mods' => [],
                                        'indicator_flags' => ['pulpInflam'],
                                    ],
                                ],
                            ],
                        ],
                    ])]],
                ],
            ], 200),
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

    protected function makeClient(): Client
    {
        return Client::create([
            'client_code' => 'CL-3001',
            'name' => 'Sami',
            'phone' => '+963900003001',
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_it_returns_a_draft_plan_without_persisting_anything(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertOk();

        $response->assertJsonPath('data.diagnosis_summary', 'Pulp necrosis on tooth 13.')
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.duration_minutes', 30)
            ->assertJsonPath('data.sessions.0.odontogram_v2_status.teeth.13.endo', 'endo-filling-incomplete');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_it_caps_sessions_at_eight_even_if_the_model_returns_more(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $extraSessions = array_fill(0, 10, [
            'day_offset' => 1,
            'duration_minutes' => 30,
            'session_description' => 'Follow-up.',
            'teeth' => [],
        ]);
        $this->fakeOpenAiResponse($extraSessions);

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Multiple issues.',
        ])->assertOk();

        $response->assertJsonCount(8, 'data.sessions');
    }

    public function test_it_rejects_non_doctor_users(): void
    {
        $user = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($user);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor');
    }

    public function test_it_requires_a_description_or_audio(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_it_transcribes_audio_when_no_description_is_provided(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Tooth 13 has pulp necrosis.'], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Pulp necrosis on tooth 13.',
                        'sessions' => [],
                    ])]],
                ],
            ], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan", [
            'audio' => $audio,
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['messages'][1]['content'] === 'Tooth 13 has pulp necrosis.';
        });
    }
}
