<?php

namespace Tests\Feature\Nutrition;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\PatientLabResult;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiContextInjectionTest extends TestCase
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

    public function test_sending_a_message_includes_the_client_nutrition_profile_and_measurements_in_the_system_prompt(): void
    {
        $company = Company::factory()->create();
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
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
            'name' => 'Ayşe',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $nutrition->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        $this->signKvkkConsent($client);

        Sanctum::actingAs($doctor);

        $this->putJson("/api/nutrition/clients/{$client->id}/profile", [
            'height_cm' => 165,
            'dietary_type' => 'vegetarian',
            'allergies' => ['peanuts'],
            'goal' => 'weight_loss',
        ])->assertOk();

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-08-16',
            'weight_kg' => 82,
        ])->assertCreated();
        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 79,
        ])->assertCreated();

        $this->fakeChatResponse();

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'How is the patient progressing?',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();
            $systemContent = collect($body['messages'])->firstWhere('role', 'system')['content'] ?? '';

            return str_contains($systemContent, 'Height: 165')
                && str_contains($systemContent, 'Dietary type: vegetarian')
                && str_contains($systemContent, 'Allergies: peanuts')
                && str_contains($systemContent, 'Goal: weight_loss')
                && str_contains($systemContent, 'weight 82')
                && str_contains($systemContent, 'weight 79')
                && str_contains($systemContent, 'lost 3 kg');
        });
    }

    public function test_sending_a_message_includes_the_prior_confirmed_plan_and_lab_results(): void
    {
        $company = Company::factory()->create();
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
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
            'name' => 'Ayşe',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $nutrition->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        $this->signKvkkConsent($client);

        CarePlan::create([
            'company_id' => $company->id,
            'specialty_id' => $nutrition->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'created_by' => $doctor->id,
            'title' => 'September Plan',
            'diet_plan' => 'Breakfast: oats. Lunch: grilled chicken and salad.',
            'exercise_plan' => '30 minutes of walking daily.',
            'status' => CarePlan::STATUS_CONFIRMED,
        ]);

        PatientLabResult::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $nutrition->id,
            'test_name' => 'Fasting Glucose',
            'result_value' => '105',
            'unit' => 'mg/dL',
            'reference_range' => '70-100',
            'is_abnormal' => true,
            'test_date' => '2026-09-10',
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);

        Sanctum::actingAs($doctor);
        $this->fakeChatResponse();

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'How is the patient progressing?',
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();
            $systemContent = collect($body['messages'])->firstWhere('role', 'system')['content'] ?? '';

            return str_contains($systemContent, 'September Plan')
                && str_contains($systemContent, 'Breakfast: oats')
                && str_contains($systemContent, '30 minutes of walking daily')
                && str_contains($systemContent, 'Fasting Glucose')
                && str_contains($systemContent, '105 mg/dL')
                && str_contains($systemContent, 'reference 70-100')
                && str_contains($systemContent, 'flagged abnormal');
        });
    }
}
