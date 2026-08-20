<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpecialtySeeder::class);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeOpenAiResponse(?array $sessions = null, array $usage = ['prompt_tokens' => 120, 'completion_tokens' => 80, 'total_tokens' => 200]): void
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
                'usage' => $usage,
            ], 200),
        ]);
    }

    protected function activeSubscription(Company $company, ?int $maxAiTokens = null, int $aiTokensUsed = 0): Subscription
    {
        // Company::factory() auto-creates a default subscription; replace it
        // rather than stacking a second one that currentSubscription() could
        // pick instead of this test's specifically-configured one.
        $company->subscriptions()->delete();

        return Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => $maxAiTokens,
            'ai_tokens_used' => $aiTokensUsed,
        ]);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company);

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

    public function test_it_returns_a_draft_plan_without_persisting_appointments(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Tooth 13 has pulp necrosis.',
        ])->assertOk();

        $response->assertJsonPath('data.diagnosis_summary', 'Pulp necrosis on tooth 13.')
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.duration_minutes', 30)
            ->assertJsonPath('data.sessions.0.odontogram_v2_status.teeth.13.endo', 'endo-filling-incomplete')
            ->assertJsonPath('data.user_message.content', 'Tooth 13 has pulp necrosis.')
            ->assertJsonMissingPath('data.usage');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('ai_conversation_messages', 2);
    }

    public function test_it_defaults_the_trigger_message_when_no_text_is_given(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [])->assertOk();

        $this->assertDatabaseHas('ai_conversation_messages', [
            'role' => 'user',
            'content' => 'Please build a treatment plan based on our conversation so far.',
        ]);
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

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Multiple issues.',
        ])->assertOk();

        $response->assertJsonCount(8, 'data.sessions');
    }

    public function test_it_rejects_non_doctor_users(): void
    {
        $user = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($user);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor');
    }

    public function test_it_records_ai_token_usage_after_a_successful_generation(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Tooth 13 has pulp necrosis.',
        ])->assertOk();

        $subscription = $doctor->company->currentSubscription()->first();
        $this->assertSame(200, $subscription->ai_tokens_used);

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $doctor->company_id,
            'subscription_id' => $subscription->id,
            'user_id' => $doctor->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_generate',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
        ]);
    }

    public function test_it_records_ai_token_usage_even_when_slot_resolution_fails_afterward(): void
    {
        // Doctor has no doctorSchedule() at all, so DoctorAvailabilityService rejects every
        // day it's asked about and resolveSessionSlot() ultimately throws — this happens
        // AFTER the (mocked) OpenAI chat completion has already "succeeded" and consumed
        // tokens. Usage must still be recorded even though the overall request fails.
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('sessions');

        $subscription = $doctor->company->currentSubscription()->first();
        $this->assertSame(200, $subscription->ai_tokens_used);

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $doctor->company_id,
            'subscription_id' => $subscription->id,
            'user_id' => $doctor->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_generate',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
        ]);
    }

    public function test_it_blocks_generation_when_the_company_has_reached_its_ai_token_limit(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company, maxAiTokens: 100, aiTokensUsed: 100);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        Http::fake();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('ai_tokens');

        Http::assertNothingSent();
    }
}
