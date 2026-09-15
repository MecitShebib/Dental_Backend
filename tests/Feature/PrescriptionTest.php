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

    public function test_a_doctor_can_write_a_prescription_with_multiple_items_and_its_specialty_is_derived_from_them(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::DENTAL);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'prescribed_date' => '2026-09-01',
            'items' => [
                ['medication_name' => 'Amoxicillin', 'dosage_instruction' => '1x3x7', 'instructions' => 'Take with food.'],
                ['medication_name' => 'Ibuprofen', 'dosage_instruction' => '1x2x5'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::DENTAL);
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonPath('data.items.0.medication_name', 'Amoxicillin');
        $response->assertJsonPath('data.items.0.dosage_instruction', '1x3x7');
        $response->assertJsonPath('data.items.1.medication_name', 'Ibuprofen');

        $this->assertDatabaseHas('prescriptions', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
        ]);
        $this->assertDatabaseCount('prescription_items', 2);
    }

    public function test_it_rejects_a_prescription_with_no_items(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::DENTAL);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'prescribed_date' => '2026-09-01',
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
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
            'prescribed_date' => '2026-08-01',
            'items' => [['medication_name' => 'Ibuprofen']],
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/prescriptions", [
            'doctor_id' => $doctor->id,
            'prescribed_date' => '2026-08-15',
            'items' => [['medication_name' => 'Naproxen']],
        ])->assertCreated();

        $response = $this->getJson("/api/clients/{$client->id}/prescriptions")->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.items.0.medication_name', 'Naproxen');
        $response->assertJsonPath('data.1.items.0.medication_name', 'Ibuprofen');
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
            'prescribed_date' => '2026-08-10',
            'items' => [['medication_name' => 'Tretinoin']],
        ])->assertCreated();

        $prescriptionId = $created->json('data.id');

        // A full replace: the new item set entirely replaces the old one,
        // same convention the frontend's table always follows.
        $this->putJson("/api/prescriptions/{$prescriptionId}", [
            'items' => [
                ['medication_name' => 'Tretinoin', 'dosage_instruction' => '0.05%', 'instructions' => 'Apply nightly.'],
                ['medication_name' => 'Moisturizer'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.dosage_instruction', '0.05%');

        $this->assertDatabaseCount('prescription_items', 2);

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
            'prescribed_date' => '2026-08-18',
            'items' => [['medication_name' => 'Aspirin']],
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
            'prescribed_date' => '2026-08-18',
            'items' => [['medication_name' => 'Metformin']],
        ])->assertCreated();
        $prescriptionId = $created->json('data.id');

        Sanctum::actingAs($doctorB);
        $this->getJson("/api/clients/{$clientA->id}/prescriptions")->assertNotFound();
        $this->putJson("/api/prescriptions/{$prescriptionId}", ['items' => [['medication_name' => 'Hacked']]])->assertNotFound();
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
            'prescribed_date' => '2026-09-01',
            'items' => [['medication_name' => 'Amoxicillin']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client');
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_medication_suggestions_are_scoped_to_the_requesters_company_and_deduplicated(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $doctorA = $this->makeDoctor($companyA, Specialty::DENTAL);
        $doctorB = $this->makeDoctor($companyB, Specialty::DENTAL);
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);
        $this->enrollClient($clientA, $doctorA);
        $this->enrollClient($clientB, $doctorB);

        Sanctum::actingAs($doctorA);
        $this->postJson("/api/clients/{$clientA->id}/prescriptions", [
            'doctor_id' => $doctorA->id,
            'prescribed_date' => '2026-08-01',
            'items' => [['medication_name' => 'Voltaren'], ['medication_name' => 'Parol']],
        ])->assertCreated();
        $this->postJson("/api/clients/{$clientA->id}/prescriptions", [
            'doctor_id' => $doctorA->id,
            'prescribed_date' => '2026-08-02',
            'items' => [['medication_name' => 'Voltaren']],
        ])->assertCreated();

        Sanctum::actingAs($doctorB);
        $this->postJson("/api/clients/{$clientB->id}/prescriptions", [
            'doctor_id' => $doctorB->id,
            'prescribed_date' => '2026-08-01',
            'items' => [['medication_name' => 'FromOtherCompany']],
        ])->assertCreated();

        Sanctum::actingAs($doctorA);
        $response = $this->getJson('/api/prescription-medications')->assertOk();

        $response->assertJson(['data' => ['Parol', 'Voltaren']]);
    }
}
