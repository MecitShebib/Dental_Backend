<?php

namespace Tests\Feature\Pediatrics;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicalRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
        Storage::fake('local');
    }

    private function specialty(): Specialty
    {
        return Specialty::query()->where('key', Specialty::PEDIATRICS)->firstOrFail();
    }

    private function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Clinical Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    /** @return array{0: Company, 1: User, 2: Client} */
    private function ownedClient(): array
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        $client = $this->makeClient($company);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $doctor);

        return [$company, $doctor, $client];
    }

    public function test_the_profile_starts_empty_and_can_be_updated(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->getJson("/api/pediatrics/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/pediatrics/clients/{$client->id}/profile", ['gestational_age_weeks_at_birth' => 21, 'birth_weight_g' => 201, 'birth_length_cm' => 21.5, 'birth_head_circumference_cm' => 16.5, 'guardian_name' => 'Sample guardian_name', 'guardian_phone' => 'Sample guardian_phone', 'guardian_relation' => 'mother', 'feeding_type' => 'breast', 'blood_type' => 'A', 'rh' => 'positive', 'allergies' => 'Notes for allergies', 'chronic_conditions' => 'Notes for chronic_conditions', 'developmental_milestones' => [['milestone' => 'Sample milestone', 'achieved_at_months' => 1]]])->assertOk();

        $this->assertDatabaseHas('pediatrics_client_profiles', ['client_id' => $client->id, 'gestational_age_weeks_at_birth' => 21]);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/pediatrics/clients/{$client->id}/profile", ['guardian_relation' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_relation');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/pediatrics/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/pediatrics/clients/{$client->id}/growth-measurements")->assertUnprocessable();
        $this->getJson("/api/pediatrics/clients/{$client->id}/vaccinations")->assertUnprocessable();
    }

    public function test_growth_measurements_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/pediatrics/clients/{$client->id}/growth-measurements", ['measured_at' => '2026-09-10', 'weight_kg' => 1.5, 'height_cm' => 1.5, 'head_circumference_cm' => 1.5, 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/pediatrics/clients/{$client->id}/growth-measurements")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/pediatrics/growth-measurements/{$uuid}", ['measured_at' => '2026-09-11', 'weight_kg' => 2.5, 'height_cm' => 2.5, 'head_circumference_cm' => 2.5, 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.measured_at', '2026-09-11');

        $this->deleteJson("/api/pediatrics/growth-measurements/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('pediatrics_growth_measurements', ['uuid' => $uuid]);
    }

    public function test_growth_measurements_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/pediatrics/clients/{$client->id}/growth-measurements", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('measured_at');
    }

    public function test_growth_measurements_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/pediatrics/clients/{$client->id}/growth-measurements", ['measured_at' => '2026-09-10', 'weight_kg' => 1.5, 'height_cm' => 1.5, 'head_circumference_cm' => 1.5, 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/pediatrics/growth-measurements/{$uuid}", ['measured_at' => '2026-09-11', 'weight_kg' => 2.5, 'height_cm' => 2.5, 'head_circumference_cm' => 2.5, 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_vaccinations_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/pediatrics/clients/{$client->id}/vaccinations", ['administered_at' => '2026-09-10', 'vaccine' => 'hep_b', 'dose_number' => 2, 'lot_number' => 'Sample lot_number', 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/pediatrics/clients/{$client->id}/vaccinations")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/pediatrics/vaccinations/{$uuid}", ['administered_at' => '2026-09-11', 'vaccine' => 'bcg', 'dose_number' => 3, 'lot_number' => 'Sample lot_number updated', 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.administered_at', '2026-09-11');

        $this->deleteJson("/api/pediatrics/vaccinations/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('pediatrics_vaccinations', ['uuid' => $uuid]);
    }

    public function test_vaccinations_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/pediatrics/clients/{$client->id}/vaccinations", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('administered_at');
    }

    public function test_vaccinations_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/pediatrics/clients/{$client->id}/vaccinations", ['administered_at' => '2026-09-10', 'vaccine' => 'hep_b', 'dose_number' => 2, 'lot_number' => 'Sample lot_number', 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/pediatrics/vaccinations/{$uuid}", ['administered_at' => '2026-09-11', 'vaccine' => 'bcg', 'dose_number' => 3, 'lot_number' => 'Sample lot_number updated', 'notes' => 'Notes for notes updated'])->assertNotFound();
    }
}
