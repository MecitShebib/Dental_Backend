<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientLabResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function makeDoctor(Company $company, string $specialtyKey): User
    {
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();

        return User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $specialty->id,
        ]);
    }

    public function test_a_doctor_can_record_a_lab_result_and_its_specialty_is_derived_from_them(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $doctor->id,
            'test_name' => 'Hemoglobin A1c',
            'result_value' => '5.4',
            'unit' => '%',
            'reference_range' => '4.0-5.6',
            'is_abnormal' => false,
            'test_date' => '2026-08-18',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.test_name', 'Hemoglobin A1c');
        $response->assertJsonPath('data.specialty_key', Specialty::GYNECOLOGY);

        $this->assertDatabaseHas('patient_lab_results', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'test_name' => 'Hemoglobin A1c',
        ]);
    }

    public function test_lab_results_are_listed_for_a_client_ordered_by_test_date(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::ORTHOPEDICS);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $doctor->id,
            'test_name' => 'X-Ray - Knee',
            'test_date' => '2026-08-01',
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $doctor->id,
            'test_name' => 'Bone Density Scan',
            'test_date' => '2026-08-15',
        ])->assertCreated();

        $response = $this->getJson("/api/clients/{$client->id}/lab-results")->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.test_name', 'Bone Density Scan');
        $response->assertJsonPath('data.1.test_name', 'X-Ray - Knee');
    }

    public function test_a_lab_result_can_be_updated_and_deleted(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::COSMETIC);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $doctor->id,
            'test_name' => 'Skin Allergy Patch Test',
            'test_date' => '2026-08-10',
        ])->assertCreated();

        $labResultId = $created->json('data.id');

        $this->putJson("/api/lab-results/{$labResultId}", [
            'result_value' => 'Negative',
            'is_abnormal' => false,
        ])->assertOk()
            ->assertJsonPath('data.result_value', 'Negative');

        $this->deleteJson("/api/lab-results/{$labResultId}")->assertOk();

        $this->assertSoftDeleted('patient_lab_results', ['id' => $labResultId]);
    }

    public function test_it_rejects_a_doctor_with_no_specialty_assigned(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => null]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $doctor->id,
            'test_name' => 'Vital Signs Check',
            'test_date' => '2026-08-18',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');

        $this->assertDatabaseCount('patient_lab_results', 0);
    }

    public function test_lab_results_are_scoped_to_the_requesters_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $doctorA = $this->makeDoctor($companyA, Specialty::INTERNAL_MEDICINE);
        $doctorB = $this->makeDoctor($companyB, Specialty::INTERNAL_MEDICINE);
        $clientA = $this->makeClient($companyA);

        Sanctum::actingAs($doctorA);
        $created = $this->postJson("/api/clients/{$clientA->id}/lab-results", [
            'doctor_id' => $doctorA->id,
            'test_name' => 'Lipid Panel',
            'test_date' => '2026-08-18',
        ])->assertCreated();
        $labResultId = $created->json('data.id');

        Sanctum::actingAs($doctorB);
        $this->getJson("/api/clients/{$clientA->id}/lab-results")->assertNotFound();
        $this->putJson("/api/lab-results/{$labResultId}", ['test_name' => 'Hacked'])->assertNotFound();
    }
}
