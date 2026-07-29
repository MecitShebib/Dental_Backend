<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranscribeAiTreatmentPlanAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-key']);
    }

    protected function activeSubscription(Company $company, ?int $maxAiTokens = null, int $aiTokensUsed = 0): Subscription
    {
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

    protected function makeClient(): Client
    {
        return Client::create([
            'client_code' => 'CL-4001',
            'name' => 'Rana',
            'phone' => '+963900004001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_it_transcribes_a_recording_into_text(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Tooth 13 has pulp necrosis.'], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/transcribe", [
            'audio' => $audio,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Tooth 13 has pulp necrosis.');

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions');
    }

    public function test_it_rejects_non_doctor_users(): void
    {
        $user = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($user);
        $client = $this->makeClient();

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/transcribe", [
            'audio' => $audio,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('doctor');
    }

    public function test_it_requires_an_audio_file(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/transcribe", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');
    }

    public function test_it_is_not_blocked_by_the_companys_ai_token_limit(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company, maxAiTokens: 100, aiTokensUsed: 100);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Still transcribed.'], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/transcribe", [
            'audio' => $audio,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Still transcribed.');
    }
}
