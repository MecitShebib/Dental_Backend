<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrescriptionTest extends TestCase
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

    // In real usage a client is only ever created (and thus enrolled, via
    // ClientSpecialtyEnrollmentService) through ClientController::store() --
    // this helper stands in for that so these fixtures match how
    // assertActingDoctorOwnsClient() actually sees a patient in production.
    protected function enrollClient(Client $client, User $doctor): void
    {
        ClientSpecialtyRecord::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'specialty_id' => $doctor->specialty_id,
            'primary_doctor_id' => $doctor->id,
            'created_by' => $doctor->id,
        ]);
    }

    public function test_a_doctor_can_write_a_prescription_and_its_specialty_is_derived_from_them(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::DENTAL);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'medication_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'frequency' => '3x daily',
            'duration' => '7 days',
            'instructions' => 'Take with food.',
            'prescribed_date' => '2026-09-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.medication_name', 'Amoxicillin');
        $response->assertJsonPath('data.specialty_key', Specialty::DENTAL);

        $this->assertDatabaseHas('prescriptions', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'medication_name' => 'Amoxicillin',
        ]);
    }

    public function test_prescriptions_are_listed_for_a_client_ordered_by_prescribed_date(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::ORTHOPEDICS);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'medication_name' => 'Ibuprofen',
            'prescribed_date' => '2026-08-01',
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'medication_name' => 'Naproxen',
            'prescribed_date' => '2026-08-15',
        ])->assertCreated();

        $response = $this->getJson("/api/clients/{$client->id}/prescriptions")->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.medication_name', 'Naproxen');
        $response->assertJsonPath('data.1.medication_name', 'Ibuprofen');
    }

    public function test_a_prescription_can_be_updated_and_deleted(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::COSMETIC);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'medication_name' => 'Tretinoin',
            'prescribed_date' => '2026-08-10',
        ])->assertCreated();

        $prescriptionId = $created->json('data.id');

        $this->putJson("/api/prescriptions/{$prescriptionId}", [
            'dosage' => '0.05%',
            'instructions' => 'Apply nightly.',
        ])->assertOk()
            ->assertJsonPath('data.dosage', '0.05%');

        $this->deleteJson("/api/prescriptions/{$prescriptionId}")->assertOk();

        $this->assertSoftDeleted('prescriptions', ['id' => $prescriptionId]);
    }

    public function test_it_rejects_a_doctor_with_no_specialty_assigned(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => null]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'medication_name' => 'Aspirin',
            'prescribed_date' => '2026-08-18',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor_id');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_prescriptions_are_scoped_to_the_requesters_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $doctorA = $this->makeDoctor($companyA, Specialty::INTERNAL_MEDICINE);
        $doctorB = $this->makeDoctor($companyB, Specialty::INTERNAL_MEDICINE);
        $clientA = $this->makeClient($companyA);
        $this->enrollClient($clientA, $doctorA);

        Sanctum::actingAs($doctorA);
        $created = $this->postJson("/api/clients/{$clientA->id}/prescriptions", [
            'doctor_id' => $doctorA->id,
            'medication_name' => 'Metformin',
            'prescribed_date' => '2026-08-18',
        ])->assertCreated();
        $prescriptionId = $created->json('data.id');

        Sanctum::actingAs($doctorB);
        $this->getJson("/api/clients/{$clientA->id}/prescriptions")->assertNotFound();
        $this->putJson("/api/prescriptions/{$prescriptionId}", ['medication_name' => 'Hacked'])->assertNotFound();
    }

    public function test_a_doctor_cannot_write_a_prescription_for_a_colleagues_patient(): void
    {
        // Security regression test: store() used to resolve/pin doctor_id to
        // the acting doctor (blocking impersonation) but never actually
        // checked whether $client belonged to them, so any doctor in the
        // same company could write a prescription onto a colleague's patient
        // just by knowing the client id.
        $company = Company::factory()->create();
        $owningDoctor = $this->makeDoctor($company, Specialty::DENTAL);
        $otherDoctor = $this->makeDoctor($company, Specialty::DENTAL);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $owningDoctor);

        Sanctum::actingAs($otherDoctor);
        $response = $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $otherDoctor->id,
            'medication_name' => 'Amoxicillin',
            'prescribed_date' => '2026-09-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client');
        $this->assertDatabaseCount('prescriptions', 0);
    }
}
