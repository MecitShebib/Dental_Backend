<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function makeClient(Company $company, string $name): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_index_only_returns_nutrition_patients_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);

        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $nutritionPatient = $this->makeClient($company, 'Nutrition Patient');
        $dentalPatient = $this->makeClient($company, 'Dental Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($nutritionPatient, $nutrition, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($dentalPatient, $dental, $manager);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/nutrition/clients');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Nutrition Patient'));
        $this->assertFalse($names->contains('Dental Patient'));
    }

    public function test_store_enrolls_the_new_patient_as_nutrition_without_a_specialty_id_in_the_payload(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/nutrition/clients', [
            'name' => 'New Nutrition Patient',
            'phone' => '+15551232222',
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $client = Client::where('name', 'New Nutrition Patient')->firstOrFail();
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::NUTRITION)->value('id'),
        ]);
    }

    public function test_a_manager_can_filter_patients_by_their_primary_doctor(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);

        $patientA = $this->makeClient($company, 'Patient Of A');
        $patientB = $this->makeClient($company, 'Patient Of B');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($patientA, $doctorA);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($patientB, $doctorB);

        Sanctum::actingAs($manager);

        $all = collect($this->getJson('/api/nutrition/clients')->assertOk()->json('data'))->pluck('name');
        $this->assertTrue($all->contains('Patient Of A'));
        $this->assertTrue($all->contains('Patient Of B'));

        $onlyA = collect($this->getJson("/api/nutrition/clients?doctor_id={$doctorA->id}")->assertOk()->json('data'))->pluck('name');
        $this->assertSame(['Patient Of A'], $onlyA->all());
    }

    public function test_a_doctor_cannot_use_the_doctor_filter_to_see_another_doctors_patients(): void
    {
        $company = Company::factory()->create();
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $nutrition->id]);
        $patientB = $this->makeClient($company, 'Patient Of B');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($patientB, $doctorB);

        Sanctum::actingAs($doctorA);

        $this->getJson("/api/nutrition/clients?doctor_id={$doctorB->id}")->assertOk()->assertJsonCount(0, 'data');
    }
}
