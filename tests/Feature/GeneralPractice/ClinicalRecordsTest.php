<?php

namespace Tests\Feature\GeneralPractice;

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
        return Specialty::query()->where('key', Specialty::GENERAL_PRACTICE)->firstOrFail();
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

        $this->getJson("/api/general_practice/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/general_practice/clients/{$client->id}/profile", ['chronic_conditions' => ['diabetes_t1', 'diabetes_t2'], 'current_medications' => 'Notes for current_medications', 'allergies' => 'Notes for allergies', 'blood_type' => 'A', 'rh' => 'positive', 'smoking' => 'never', 'alcohol' => 'none', 'height_cm' => 31.5, 'adult_vaccination_status' => ['tetanus' => '2026-01-01']])->assertOk();

        $this->assertDatabaseHas('general_practice_client_profiles', ['client_id' => $client->id, 'current_medications' => 'Notes for current_medications']);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/general_practice/clients/{$client->id}/profile", ['blood_type' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('blood_type');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/general_practice/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/general_practice/clients/{$client->id}/vitals")->assertUnprocessable();
        $this->getJson("/api/general_practice/clients/{$client->id}/referrals")->assertUnprocessable();
    }

    public function test_vitals_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/general_practice/clients/{$client->id}/vitals", ['measured_at' => '2026-09-10', 'systolic' => 41, 'diastolic' => 21, 'pulse' => 21, 'temperature_c' => 31.5, 'spo2' => 51, 'blood_glucose' => 11, 'weight_kg' => 1.5, 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/general_practice/clients/{$client->id}/vitals")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/general_practice/vitals/{$uuid}", ['measured_at' => '2026-09-11', 'systolic' => 42, 'diastolic' => 22, 'pulse' => 22, 'temperature_c' => 32.5, 'spo2' => 52, 'blood_glucose' => 12, 'weight_kg' => 2.5, 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.measured_at', '2026-09-11');

        $this->deleteJson("/api/general_practice/vitals/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('general_practice_vitals', ['uuid' => $uuid]);
    }

    public function test_vitals_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/general_practice/clients/{$client->id}/vitals", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('measured_at');
    }

    public function test_vitals_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/general_practice/clients/{$client->id}/vitals", ['measured_at' => '2026-09-10', 'systolic' => 41, 'diastolic' => 21, 'pulse' => 21, 'temperature_c' => 31.5, 'spo2' => 51, 'blood_glucose' => 11, 'weight_kg' => 1.5, 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/general_practice/vitals/{$uuid}", ['measured_at' => '2026-09-11', 'systolic' => 42, 'diastolic' => 22, 'pulse' => 22, 'temperature_c' => 32.5, 'spo2' => 52, 'blood_glucose' => 12, 'weight_kg' => 2.5, 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_referrals_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/general_practice/clients/{$client->id}/referrals", ['referred_at' => '2026-09-10', 'target_specialty' => 'Sample target_specialty', 'target_institution' => 'Sample target_institution', 'reason' => 'Notes for reason', 'status' => 'pending'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/general_practice/clients/{$client->id}/referrals")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/general_practice/referrals/{$uuid}", ['referred_at' => '2026-09-11', 'target_specialty' => 'Sample target_specialty updated', 'target_institution' => 'Sample target_institution updated', 'reason' => 'Notes for reason updated', 'status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.referred_at', '2026-09-11');

        $this->deleteJson("/api/general_practice/referrals/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('general_practice_referrals', ['uuid' => $uuid]);
    }

    public function test_referrals_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/general_practice/clients/{$client->id}/referrals", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referred_at');
    }

    public function test_referrals_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/general_practice/clients/{$client->id}/referrals", ['referred_at' => '2026-09-10', 'target_specialty' => 'Sample target_specialty', 'target_institution' => 'Sample target_institution', 'reason' => 'Notes for reason', 'status' => 'pending'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/general_practice/referrals/{$uuid}", ['referred_at' => '2026-09-11', 'target_specialty' => 'Sample target_specialty updated', 'target_institution' => 'Sample target_institution updated', 'reason' => 'Notes for reason updated', 'status' => 'completed'])->assertNotFound();
    }
}
