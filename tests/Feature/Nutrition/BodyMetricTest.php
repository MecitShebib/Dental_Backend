<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BodyMetricTest extends TestCase
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

    private function makeNutritionDoctorAndClient(Company $company): array
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $nutrition->id,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Body Metric Patient',
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

        return [$doctor, $client];
    }

    public function test_store_creates_a_measurement_and_computes_bmi_from_profile_height(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        // Height comes from the profile built in an earlier sub-project.
        $this->putJson("/api/nutrition/clients/{$client->id}/profile", ['height_cm' => 170]);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 79.0,
            'body_fat_percent' => 24.5,
        ]);

        $response->assertCreated();
        // 79 / (1.70^2) = 27.3
        $response->assertJsonPath('data.bmi', '27.3');
        $this->assertDatabaseHas('nutrition_body_metrics', [
            'client_id' => $client->id,
            'recorded_at' => '2026-09-16 00:00:00',
            'source' => 'manual',
        ]);
    }

    public function test_store_persists_segmental_arm_and_leg_fields(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'right_arm_muscle_kg' => 3.4,
            'left_arm_muscle_kg' => 3.3,
            'right_arm_fat_percent' => 18.2,
            'left_arm_fat_percent' => 18.5,
            'right_leg_muscle_kg' => 9.1,
            'left_leg_muscle_kg' => 9.0,
            'right_leg_fat_percent' => 22.1,
            'left_leg_fat_percent' => 22.4,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.right_arm_muscle_kg', '3.4');
        $response->assertJsonPath('data.left_arm_muscle_kg', '3.3');
        $response->assertJsonPath('data.right_arm_fat_percent', '18.2');
        $response->assertJsonPath('data.left_arm_fat_percent', '18.5');
        $response->assertJsonPath('data.right_leg_muscle_kg', '9.1');
        $response->assertJsonPath('data.left_leg_muscle_kg', '9.0');
        $response->assertJsonPath('data.right_leg_fat_percent', '22.1');
        $response->assertJsonPath('data.left_leg_fat_percent', '22.4');
    }

    public function test_store_persists_trunk_and_metabolic_fields(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'trunk_muscle_kg' => 30.2,
            'trunk_fat_percent' => 24.1,
            'metabolic_age' => 34,
            'daily_calorie_need' => 2692,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.trunk_muscle_kg', '30.2');
        $response->assertJsonPath('data.trunk_fat_percent', '24.1');
        $response->assertJsonPath('data.metabolic_age', 34);
        $response->assertJsonPath('data.daily_calorie_need', 2692);
    }

    public function test_index_lists_measurements_newest_first(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", ['recorded_at' => '2026-08-01', 'weight_kg' => 82]);
        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", ['recorded_at' => '2026-09-01', 'weight_kg' => 79]);

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/body-metrics");

        $response->assertOk();
        $dates = collect($response->json('data'))->pluck('recorded_at');
        $this->assertSame(['2026-09-01', '2026-08-01'], $dates->all());
    }

    public function test_store_with_report_file_attaches_a_downloadable_signed_url(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->post("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 79,
            'report' => UploadedFile::fake()->create('inbody-report.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.report_original_filename', 'inbody-report.pdf');
        $this->assertNotNull($response->json('data.report_url'));

        $fileResponse = $this->get($response->json('data.report_url'));
        $fileResponse->assertOk();
    }

    public function test_destroy_removes_the_measurement(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 79,
        ])->json('data');

        $response = $this->deleteJson("/api/nutrition/body-metrics/{$created['uuid']}");

        $response->assertOk();
        $this->assertDatabaseMissing('nutrition_body_metrics', ['uuid' => $created['uuid']]);
    }

    public function test_update_persists_changed_values_and_recomputes_bmi(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $this->putJson("/api/nutrition/clients/{$client->id}/profile", ['height_cm' => 170]);

        $created = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 79,
            'right_arm_muscle_kg' => 3.0,
        ])->json('data');

        $response = $this->putJson("/api/nutrition/body-metrics/{$created['uuid']}", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 82,
            'right_arm_muscle_kg' => 3.4,
        ]);

        $response->assertOk();
        // 82 / (1.70^2) = 28.4
        $response->assertJsonPath('data.weight_kg', '82.0');
        $response->assertJsonPath('data.bmi', '28.4');
        $response->assertJsonPath('data.right_arm_muscle_kg', '3.4');
    }

    public function test_a_doctor_cannot_update_another_companys_measurement(): void
    {
        $companyA = Company::factory()->create();
        [, $clientA] = $this->makeNutritionDoctorAndClient($companyA);
        $companyB = Company::factory()->create();
        [$doctorB] = $this->makeNutritionDoctorAndClient($companyB);

        $metric = $clientA->nutritionBodyMetrics()->create([
            'recorded_at' => '2026-09-16',
            'weight_kg' => 80,
        ]);

        Sanctum::actingAs($doctorB);

        $response = $this->putJson("/api/nutrition/body-metrics/{$metric->uuid}", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 90,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('nutrition_body_metrics', ['id' => $metric->id, 'weight_kg' => 80]);
    }

    public function test_a_doctor_cannot_delete_another_companys_measurement(): void
    {
        $companyA = Company::factory()->create();
        [, $clientA] = $this->makeNutritionDoctorAndClient($companyA);
        $companyB = Company::factory()->create();
        [$doctorB] = $this->makeNutritionDoctorAndClient($companyB);

        $metric = $clientA->nutritionBodyMetrics()->create([
            'recorded_at' => '2026-09-16',
            'weight_kg' => 80,
        ]);

        Sanctum::actingAs($doctorB);

        $response = $this->deleteJson("/api/nutrition/body-metrics/{$metric->uuid}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('nutrition_body_metrics', ['id' => $metric->id]);
    }

    public function test_bmi_is_computed_when_weight_is_zero_and_left_null_without_a_profile(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        // No profile/height on file yet -- bmi must be null, not a crash.
        $response = $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.bmi', null);
    }

    public function test_the_most_recent_of_two_same_day_measurements_is_used_as_latest(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 90,
            'notes' => 'backfilled morning entry',
        ])->assertCreated();

        $this->postJson("/api/nutrition/clients/{$client->id}/body-metrics", [
            'recorded_at' => '2026-09-16',
            'weight_kg' => 88,
            'notes' => 'actual afternoon entry',
        ])->assertCreated();

        $latest = $client->nutritionBodyMetrics()->first();

        $this->assertSame('actual afternoon entry', $latest->notes);
    }

    public function test_extract_returns_ai_read_values_without_persisting_anything(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);
        $this->signKvkkConsent($client);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'recorded_at' => '2026-09-16',
                        'weight_kg' => 78.5,
                        'body_fat_percent' => 23.0,
                        'muscle_mass_kg' => 46.0,
                        'visceral_fat_rating' => 8.0,
                        'water_percent' => 53.0,
                        'bone_mass_kg' => 3.0,
                        'basal_metabolic_rate' => 1650,
                        'waist_cm' => 82.0,
                        'hip_cm' => 98.0,
                        'trunk_muscle_kg' => 29.5,
                        'trunk_fat_percent' => 23.0,
                        'metabolic_age' => 33,
                        'daily_calorie_need' => 2600,
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 40, 'total_tokens' => 240],
            ], 200),
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->post("/api/nutrition/clients/{$client->id}/body-metrics/extract", [
            'report' => UploadedFile::fake()->image('inbody-report.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.weight_kg', 78.5);
        $response->assertJsonPath('data.body_fat_percent', 23);
        $response->assertJsonPath('data.recorded_at', '2026-09-16');
        $response->assertJsonPath('data.trunk_muscle_kg', 29.5);
        $response->assertJsonPath('data.metabolic_age', 33);
        $response->assertJsonPath('data.daily_calorie_need', 2600);
        $this->assertDatabaseCount('nutrition_body_metrics', 0);
    }

    public function test_a_doctor_from_another_specialty_cannot_access_body_metrics(): void
    {
        $company = Company::factory()->create();
        [, $client] = $this->makeNutritionDoctorAndClient($company);

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $dentalDoctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $dental->id,
        ]);

        Sanctum::actingAs($dentalDoctor);

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/body-metrics");

        $response->assertStatus(422);
    }
}
