<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the new per-doctor ai_enabled toggle (User Management) --
 * assertCanUseAiAssistant() in every specialty's AiConversationController
 * (plus dental's AiTreatmentPlanController) now also checks this flag for a
 * doctor, while a System Manager stays unaffected by it.
 */
class AiAssistantAccessToggleTest extends TestCase
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

    protected function fakeChatResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reply' => 'Understood.',
                        'options' => null,
                        'ready_for_plan' => false,
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
            ], 200),
        ]);
    }

    protected function makeCompanyAndClient(Specialty $specialty): array
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Sara',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        return [$company, $client];
    }

    public function test_a_doctor_with_ai_disabled_cannot_use_the_ai_assistant(): void
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        [$company, $client] = $this->makeCompanyAndClient($nutrition);
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $nutrition->id,
            'ai_enabled' => false,
        ]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $nutrition->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertStatus(422);
    }

    public function test_a_doctor_with_ai_enabled_can_use_the_ai_assistant(): void
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        [$company, $client] = $this->makeCompanyAndClient($nutrition);
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $nutrition->id,
            'ai_enabled' => true,
        ]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $nutrition->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        Sanctum::actingAs($doctor);
        $this->fakeChatResponse();

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();
    }

    public function test_a_system_manager_can_use_the_ai_assistant_regardless_of_ai_enabled(): void
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        [$company, $client] = $this->makeCompanyAndClient($nutrition);
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => false,
            'ai_enabled' => false,
        ]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);
        Sanctum::actingAs($manager);
        $this->fakeChatResponse();

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What should we do next?',
        ])->assertOk();
    }
}
