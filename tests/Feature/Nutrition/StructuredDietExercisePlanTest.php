<?php

namespace Tests\Feature\Nutrition;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Diet plans are a fixed table (3 options each for breakfast / lunch /
 * dinner / snacks + calories, water, notes) and exercise plans a Saturday..
 * Friday table (activity + minutes per day + notes), each with a plan date --
 * filled by the AI or by hand, stored as JSON (diet_plan_data /
 * exercise_plan_data) with a readable text rendering kept in the legacy
 * diet_plan / exercise_plan columns.
 */
class StructuredDietExercisePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
        config(['services.openai.api_key' => 'test-key', 'services.openai.chat_model' => 'gpt-4o-mini']);
    }

    protected function dietitian(): User
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $nutrition->id]);
        Subscription::create([
            'company_id' => $doctor->company_id,
            'specialty_id' => $nutrition->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        $schedule = $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30]);
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function client(User $doctor): Client
    {
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Structured Plan Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $doctor);

        return $client;
    }

    protected function dietData(): array
    {
        return [
            'plan_date' => '2026-10-01',
            'breakfast' => ['Oatmeal with berries', 'Eggs and toast', 'Greek yogurt'],
            'lunch' => ['Grilled chicken salad', 'Lentil soup', 'Tuna wrap'],
            'dinner' => ['Baked salmon', 'Turkey meatballs', 'Vegetable stir fry'],
            'snacks' => ['Apple', 'Almonds'],
            'daily_calories' => 1800,
            'water_liters' => 2.5,
            'notes' => 'Avoid sugary drinks.',
        ];
    }

    protected function exerciseData(): array
    {
        return [
            'plan_date' => '2026-10-01',
            'days' => [
                'saturday' => ['activity' => 'Brisk walk', 'duration_minutes' => 30],
                'monday' => ['activity' => 'Cycling', 'duration_minutes' => 45],
                'friday' => ['activity' => 'Yoga', 'duration_minutes' => 20],
            ],
            'notes' => 'Warm up 5 minutes.',
        ];
    }

    public function test_a_dietitian_saves_a_structured_diet_plan_by_hand(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'diet',
            'data' => $this->dietData(),
        ])->assertCreated();

        $response->assertJsonPath('data.diet_plan_data.plan_date', '2026-10-01')
            ->assertJsonPath('data.diet_plan_data.breakfast.0', 'Oatmeal with berries')
            ->assertJsonPath('data.diet_plan_data.snacks', ['Apple', 'Almonds', ''])
            ->assertJsonPath('data.diet_plan_data.daily_calories', 1800)
            ->assertJsonPath('data.diet_plan_data.water_liters', 2.5);
        $this->assertStringContainsString('Breakfast', $response->json('data.diet_plan'));
        $this->assertStringContainsString('Oatmeal with berries', $response->json('data.diet_plan'));
    }

    public function test_a_structured_exercise_plan_always_has_all_seven_days_starting_saturday(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'exercise',
            'data' => $this->exerciseData(),
        ])->assertCreated();

        $days = $response->json('data.exercise_plan_data.days');
        $this->assertSame(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'], array_keys($days));
        $this->assertSame(['activity' => 'Cycling', 'duration_minutes' => 45], $days['monday']);
        $this->assertSame(['activity' => '', 'duration_minutes' => null], $days['sunday']);
        $this->assertStringContainsString('Saturday: Brisk walk (30 min)', $response->json('data.exercise_plan'));
    }

    public function test_updating_a_plan_replaces_its_table(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", ['kind' => 'diet', 'data' => $this->dietData()])->json('data.uuid');

        $this->putJson("/api/nutrition/diet-exercise-plans/{$uuid}", [
            'kind' => 'diet',
            'data' => [...$this->dietData(), 'plan_date' => '2026-10-15', 'breakfast' => ['Smoothie']],
        ])->assertOk()
            ->assertJsonPath('data.diet_plan_data.plan_date', '2026-10-15')
            ->assertJsonPath('data.diet_plan_data.breakfast', ['Smoothie', '', '']);
    }

    public function test_invalid_structured_values_are_rejected(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'exercise',
            'data' => ['days' => ['funday' => ['activity' => 'x'], 'monday' => ['activity' => 'Run', 'duration_minutes' => 9999]]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['data.days', 'data.days.monday.duration_minutes']);

        $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'diet',
            'data' => ['breakfast' => ['a', 'b', 'c', 'd']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['data.breakfast']);
    }

    public function test_the_ai_generates_structured_tables_dated_today(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);
        $exerciseDays = [];
        foreach (['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            $exerciseDays[$day] = ['activity' => $day === 'monday' ? 'Swimming' : '', 'duration_minutes' => $day === 'monday' ? 40 : null];
        }
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'diagnosis_summary' => 'Weight loss.',
                    'sessions' => [['day_offset' => 0, 'duration_minutes' => 30, 'session_description' => 'Consultation.', 'procedures' => []]],
                    'diet_plan' => [
                        'breakfast' => ['Oats', 'Eggs', 'Yogurt'], 'lunch' => ['Salad', 'Soup', 'Wrap'], 'dinner' => ['Fish', 'Chicken', 'Beans'],
                        'snacks' => ['Apple', 'Nuts', 'Carrots'], 'daily_calories' => 1700, 'water_liters' => 2, 'notes' => 'No soda.',
                    ],
                    'exercise_plan' => ['days' => $exerciseDays, 'notes' => 'Stretch daily.'],
                ])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 60, 'total_tokens' => 160],
            ], 200),
        ]);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/generate", ['text' => 'Needs weight loss.'])->assertOk();

        $response->assertJsonPath('data.diet_plan_data.plan_date', now()->toDateString())
            ->assertJsonPath('data.diet_plan_data.lunch.2', 'Wrap')
            ->assertJsonPath('data.exercise_plan_data.days.monday.activity', 'Swimming')
            ->assertJsonPath('data.exercise_plan_data.plan_date', now()->toDateString());
        $this->assertStringContainsString('Monday: Swimming (40 min)', $response->json('data.exercise_plan'));
    }

    public function test_confirm_stores_the_reviewed_tables_on_the_care_plan(): void
    {
        $doctor = $this->dietitian();
        $client = $this->client($doctor);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [['date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'duration_minutes' => 30, 'session_description' => 'Consultation.']],
            'diet_plan_data' => $this->dietData(),
            'exercise_plan_data' => $this->exerciseData(),
        ])->assertCreated()
            ->assertJsonPath('data.care_plan.diet_plan_data.dinner.0', 'Baked salmon')
            ->assertJsonPath('data.care_plan.exercise_plan_data.days.friday.activity', 'Yoga');

        $plan = CarePlan::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame('Baked salmon', $plan->diet_plan_data['dinner'][0]);
        $this->assertStringContainsString('Friday: Yoga (20 min)', $plan->exercise_plan);
    }
}
