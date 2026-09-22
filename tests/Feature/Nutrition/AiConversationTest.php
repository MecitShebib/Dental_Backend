<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiConversationTest extends TestCase
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

    protected function fakeOpenAiResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Nutrition consultation.',
                        'sessions' => [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Initial nutrition consultation.',
                                'procedures' => [
                                    ['procedure_code' => 'nutrition_consultation', 'notes' => null],
                                ],
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 60, 'total_tokens' => 160],
            ], 200),
        ]);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $nutrition->id]);
        $doctor->company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);

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

    protected function makeClient(Company $company, array $attributes = []): Client
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Sara',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
            ...$attributes,
        ]);
        $this->signKvkkConsent($client);

        return $client;
    }

    protected function systemPromptFromLastRequest(): string
    {
        $captured = null;

        Http::assertSent(function ($request) use (&$captured) {
            $captured = collect($request->data()['messages'])->firstWhere('role', 'system')['content'] ?? '';

            return true;
        });

        return (string) $captured;
    }

    public function test_it_generates_a_plan_using_the_procedure_vocabulary_not_teeth(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeOpenAiResponse();

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Client wants an initial nutrition consultation.',
        ])->assertOk();

        $response->assertJsonPath('data.diagnosis_summary', 'Nutrition consultation.')
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.procedures.0.procedure_code', 'nutrition_consultation')
            ->assertJsonMissingPath('data.sessions.0.teeth');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseHas('ai_conversations', ['client_id' => $client->id]);
    }

    public function test_it_confirms_a_plan_and_creates_an_appointment_enrolled_as_nutrition(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [
                [
                    'date' => now()->addDay()->toDateString(),
                    'start_time' => '10:00',
                    'duration_minutes' => 30,
                    'session_description' => 'Nutrition consultation.',
                    'charge_items' => [
                        ['description' => 'Nutrition Consultation', 'amount' => 250],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::NUTRITION)->value('id'),
        ]);
    }

    /**
     * The mid-conversation language flip (QA audit 2026-09-22 section 2.2) was
     * reproduced here as well as in dental, i.e. it was the shared
     * soft-inference prompt pattern, not one specialty's wording -- so the
     * non-dental prompts (SpecialtyAiProfiles) get the same explicit pin.
     */
    public function test_chat_prompt_pins_the_conversation_language_explicitly(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company, ['preferred_language' => 'tr']);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'reply' => 'Anlasildi.',
                    'options' => [],
                    'ready_for_plan' => false,
                ])]]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
            ], 200),
        ]);

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'The client wants to lose weight.',
        ])->assertOk();

        $this->assertDatabaseHas('ai_conversations', ['client_id' => $client->id, 'language' => 'tr']);

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('This conversation is conducted in Turkish.', $prompt);
        $this->assertStringContainsString('never switch to another language', $prompt);
        $this->assertStringNotContainsString('same language the doctor is writing in', $prompt);
    }

    public function test_plan_prompt_including_the_diet_addendum_names_the_pinned_language(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company, ['preferred_language' => 'tr']);
        $this->fakeOpenAiResponse();

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Build the plan.',
        ])->assertOk();

        $this->assertDatabaseHas('ai_conversations', ['client_id' => $client->id, 'language' => 'tr']);

        $prompt = $this->systemPromptFromLastRequest();
        $this->assertStringContainsString('This case has been discussed in Turkish', $prompt);
        $this->assertStringContainsString('Also write two more fields, both in Turkish', $prompt);
        $this->assertStringNotContainsString('same language the doctor used', $prompt);
    }
}
