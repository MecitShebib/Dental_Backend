<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BodyMetricTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
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
