<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequiresKvkkConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function activeDoctor(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);

        return $doctor;
    }

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-KVKK-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Unconsented Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_sending_an_ai_message_is_blocked_without_a_signed_kvkk_consent(): void
    {
        $doctor = $this->activeDoctor();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['message' => 'Patient has a toothache.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client');
    }

    public function test_sending_an_ai_message_is_allowed_once_the_kvkk_consent_is_signed(): void
    {
        $doctor = $this->activeDoctor();
        $client = $this->makeClient($doctor->company_id);
        $this->signKvkkConsent($client);
        Sanctum::actingAs($doctor);

        config(['services.openai.api_key' => 'test-key']);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['reply' => 'Tell me more.', 'options' => [], 'ready_for_plan' => false])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Patient has a toothache.'])
            ->assertOk();
    }

    public function test_ai_conversation_history_is_readable_without_a_signed_kvkk_consent(): void
    {
        // History is a read of what already happened, not a new transfer to
        // OpenAI -- only the write endpoints (send-message/transcribe/
        // generate/confirm) are gated.
        $doctor = $this->activeDoctor();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/clients/{$client->id}/ai-conversation")->assertOk();
    }

    public function test_the_gate_can_be_temporarily_disabled_via_config(): void
    {
        config(['services.kvkk.ai_consent_required' => false]);

        $doctor = $this->activeDoctor();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        config(['services.openai.api_key' => 'test-key']);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['reply' => 'Tell me more.', 'options' => [], 'ready_for_plan' => false])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Patient has a toothache.'])
            ->assertOk();
    }

    public function test_a_consent_template_of_a_different_kind_does_not_satisfy_the_gate(): void
    {
        $doctor = $this->activeDoctor();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $clinicalTemplate = ConsentTemplate::create([
            'company_id' => $doctor->company_id,
            'kind' => ConsentTemplate::KIND_CLINICAL,
            'title' => 'Procedure consent',
            'body' => 'Body',
            'language' => 'en',
        ]);
        ClientConsent::create([
            'client_id' => $client->id,
            'consent_template_id' => $clinicalTemplate->id,
            'title' => $clinicalTemplate->title,
            'body' => $clinicalTemplate->body,
            'signature_path' => 'consent-signatures/x.png',
            'signed_at' => now(),
        ]);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['message' => 'Patient has a toothache.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client');
    }
}
