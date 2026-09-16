<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowUpCycleTest extends TestCase
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
            'name' => 'Follow-up Cycle Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);

        ClientSpecialtyRecord::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'specialty_id' => $doctor->specialty_id,
            'primary_doctor_id' => $doctor->id,
        ]);

        $this->signKvkkConsent($client);

        return $client;
    }

    public function test_care_plan_confirmation_stamps_the_current_measurement_as_baseline(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-08-01',
            'weight_kg' => 90,
        ])->assertCreated();

        $response = $this->postJson("/api/clients/{$client->id}/nutrition-care-plan/confirm", [
            'treatment_code' => 'body_composition_analysis',
            'session_count' => 1,
            'interval_days' => 30,
            'start_date' => '2026-08-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();

        $carePlan = \App\Models\CarePlan::query()->where('client_id', $client->id)->firstOrFail();
        $firstSession = $carePlan->sessions()->first();

        $baselineMetric = \App\Models\NutritionBodyMetric::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame($baselineMetric->id, $firstSession->clinical_data['baseline_metric_id']);
    }

    public function test_ai_context_compares_a_new_measurement_against_the_open_cycles_baseline(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-08-01',
            'weight_kg' => 90,
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/nutrition-care-plan/confirm", [
            'treatment_code' => 'body_composition_analysis',
            'session_count' => 1,
            'interval_days' => 30,
            'start_date' => '2026-08-01',
            'preferred_start_time' => '10:00',
        ])->assertCreated();

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-08-31',
            'weight_kg' => 87,
        ])->assertCreated();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reply' => 'Great progress.',
                        'options' => null,
                        'ready_for_plan' => false,
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 10, 'total_tokens' => 60],
            ], 200),
        ]);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'How is progress since the last check-in?',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();
            $systemContent = collect($body['messages'])->firstWhere('role', 'system')['content'] ?? '';

            return str_contains($systemContent, 'Active follow-up program')
                && str_contains($systemContent, '30 days have passed since that baseline')
                && str_contains($systemContent, 'Weight since baseline: lost 3 kg');
        });
    }
}
