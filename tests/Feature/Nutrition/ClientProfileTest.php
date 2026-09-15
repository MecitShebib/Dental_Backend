<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientProfileTest extends TestCase
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
            'name' => 'Nutrition Profile Patient',
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

    public function test_show_creates_an_empty_profile_on_first_access(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/profile");

        $response->assertOk();
        $response->assertJsonPath('data.client_id', $client->id);
        $response->assertJsonPath('data.height_cm', null);
        $this->assertDatabaseHas('nutrition_client_profiles', ['client_id' => $client->id]);
    }

    public function test_update_persists_all_fields(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $payload = [
            'height_cm' => 168.5,
            'dietary_type' => 'vegetarian',
            'allergies' => ['peanuts', 'shellfish'],
            'chronic_conditions' => ['type_2_diabetes'],
            'medications_affecting_diet' => 'Metformin 500mg',
            'smoking_status' => 'none',
            'alcohol_status' => 'occasional',
            'activity_level' => 'moderate',
            'goal' => 'weight_loss',
            'target_weight_kg' => 62.0,
            'notes' => 'Prefers gluten-free where possible.',
        ];

        $response = $this->putJson("/api/nutrition/clients/{$client->id}/profile", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.height_cm', '168.5');
        $response->assertJsonPath('data.dietary_type', 'vegetarian');
        $response->assertJsonPath('data.allergies', ['peanuts', 'shellfish']);
        $response->assertJsonPath('data.goal', 'weight_loss');
        $this->assertDatabaseHas('nutrition_client_profiles', [
            'client_id' => $client->id,
            'dietary_type' => 'vegetarian',
            'activity_level' => 'moderate',
        ]);
    }

    public function test_update_rejects_an_invalid_enum_value(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/nutrition/clients/{$client->id}/profile", [
            'dietary_type' => 'carnivore-extreme',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dietary_type']);
    }

    public function test_a_doctor_from_another_specialty_cannot_access_the_profile(): void
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

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/profile");

        $response->assertStatus(422);
    }
}
