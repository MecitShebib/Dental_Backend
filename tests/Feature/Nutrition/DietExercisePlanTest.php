<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DietExercisePlanTest extends TestCase
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

    protected function doctorWithFullWeekSchedule(): User
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $nutrition->id]);
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

    protected function makeClient(User $doctor): Client
    {
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Diet Plan Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        return $client;
    }

    protected function fakeGenerateResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Weight-loss focused nutrition plan.',
                        'sessions' => [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Initial consultation.',
                                'procedures' => [
                                    ['procedure_code' => 'nutrition_consultation', 'notes' => null],
                                ],
                            ],
                        ],
                        'diet_plan' => 'Day 1: oatmeal, salad, grilled chicken.',
                        'exercise_plan' => 'Mon/Wed/Fri: 30 min brisk walk.',
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 60, 'total_tokens' => 160],
            ], 200),
        ]);
    }

    public function test_generate_plan_returns_diet_and_exercise_plan_for_nutrition(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor);
        $this->fakeGenerateResponse();

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Client wants to lose weight.',
        ]);

        // Plans are table-shaped now (StructuredDietExercisePlanTest); a
        // plain-string answer (older AI output) is kept as the table's notes
        // rather than dropped, and still readable in the text rendering.
        $response->assertOk();
        $this->assertStringContainsString('Day 1: oatmeal, salad, grilled chicken.', $response->json('data.diet_plan'));
        $this->assertStringContainsString('Mon/Wed/Fri: 30 min brisk walk.', $response->json('data.exercise_plan'));
        $response->assertJsonPath('data.diet_plan_data.notes', 'Day 1: oatmeal, salad, grilled chicken.');
    }

    public function test_confirm_persists_a_care_plan_with_diet_and_exercise_plan(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor);

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
            'diet_plan' => 'Day 1: oatmeal, salad, grilled chicken.',
            'exercise_plan' => 'Mon/Wed/Fri: 30 min brisk walk.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.care_plan.diet_plan', 'Day 1: oatmeal, salad, grilled chicken.');
        $response->assertJsonPath('data.care_plan.exercise_plan', 'Mon/Wed/Fri: 30 min brisk walk.');

        $this->assertDatabaseHas('care_plans', [
            'client_id' => $client->id,
            'diet_plan' => 'Day 1: oatmeal, salad, grilled chicken.',
            'exercise_plan' => 'Mon/Wed/Fri: 30 min brisk walk.',
        ]);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_confirm_without_diet_or_exercise_plan_creates_no_care_plan(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [
                [
                    'date' => now()->addDay()->toDateString(),
                    'start_time' => '10:00',
                    'duration_minutes' => 30,
                    'session_description' => 'Nutrition consultation.',
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.care_plan', null);
        $this->assertDatabaseCount('care_plans', 0);
    }

    public function test_other_specialty_confirm_still_works_unchanged_without_care_plan_fields(): void
    {
        $cosmetic = Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $cosmetic->id]);
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

        Sanctum::actingAs($doctor);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Cosmetic Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        $response = $this->postJson("/api/cosmetic/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [
                [
                    'date' => now()->addDay()->toDateString(),
                    'start_time' => '10:00',
                    'duration_minutes' => 30,
                    'session_description' => 'Cosmetic consultation.',
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('appointments', 1);
    }
}
