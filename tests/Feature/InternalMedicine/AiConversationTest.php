<?php

namespace Tests\Feature\InternalMedicine;

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
                        'diagnosis_summary' => 'Chronic disease follow-up.',
                        'sessions' => [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Initial chronic disease assessment.',
                                'procedures' => [
                                    ['procedure_code' => 'chronic_initial_assessment', 'notes' => null],
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
        $internal_medicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $internal_medicine->id]);
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

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Sara',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_it_generates_a_plan_using_the_procedure_vocabulary_not_teeth(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeOpenAiResponse();

        $response = $this->postJson("/api/internal_medicine/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Patient has diabetes, needs follow-up.',
        ])->assertOk();

        $response->assertJsonPath('data.diagnosis_summary', 'Chronic disease follow-up.')
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.procedures.0.procedure_code', 'chronic_initial_assessment')
            ->assertJsonMissingPath('data.sessions.0.teeth');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseHas('ai_conversations', ['client_id' => $client->id]);
    }

    public function test_it_confirms_a_plan_and_creates_an_appointment_enrolled_as_internal_medicine(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $response = $this->postJson("/api/internal_medicine/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [
                [
                    'date' => now()->addDay()->toDateString(),
                    'start_time' => '10:00',
                    'duration_minutes' => 30,
                    'session_description' => 'Chronic disease assessment.',
                    'charge_items' => [
                        ['description' => 'Initial Chronic Disease Assessment', 'amount' => 600],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->value('id'),
        ]);
    }
}
