<?php

namespace Tests\Feature;

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

    public function test_a_doctor_can_record_a_lab_result_and_its_specialty_is_derived_from_them(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
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
        $this->enrollClient($client, $doctor);
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
        $this->enrollClient($client, $doctor);
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
        $this->enrollClient($clientA, $doctorA);

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

    public function test_a_doctor_cannot_record_a_lab_result_for_a_colleagues_patient(): void
    {
        // Security regression test: store() derived doctor_id/specialty_id
        // from the acting doctor but never checked whether $client belonged
        // to them -- any doctor in the same company could record a lab
        // result onto a colleague's patient just by knowing the client id
        // (same class of bug fixed in PrescriptionController::store()).
        $company = Company::factory()->create();
        $owningDoctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $otherDoctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $owningDoctor);

        Sanctum::actingAs($otherDoctor);
        $response = $this->postJson("/api/clients/{$client->id}/lab-results", [
            'doctor_id' => $otherDoctor->id,
            'test_name' => 'Hemoglobin A1c',
            'test_date' => '2026-09-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client');
        $this->assertDatabaseCount('patient_lab_results', 0);
    }

    public function test_analyze_returns_every_result_the_ai_reads_off_a_report_without_persisting_anything(): void
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);
        $doctor = $this->makeDoctor($company, Specialty::INTERNAL_MEDICINE);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'results' => [
                            [
                                'test_name' => 'Hemoglobin A1c',
                                'result_value' => '7.2',
                                'unit' => '%',
                                'reference_range' => '4.0-5.6',
                                'is_abnormal' => true,
                                'test_date' => '2026-09-01',
                            ],
                            [
                                'test_name' => 'Fasting Glucose',
                                'result_value' => '110',
                                'unit' => 'mg/dL',
                                'reference_range' => '70-100',
                                'is_abnormal' => true,
                                'test_date' => '2026-09-01',
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 60, 'total_tokens' => 360],
            ], 200),
        ]);

        $response = $this->postJson("/api/clients/{$client->id}/lab-results/analyze", [
            'report' => UploadedFile::fake()->image('panel-report.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data.results');
        $response->assertJsonPath('data.results.0.test_name', 'Hemoglobin A1c');
        $response->assertJsonPath('data.results.1.test_name', 'Fasting Glucose');
        $this->assertDatabaseCount('patient_lab_results', 0);
    }

    public function test_analyze_accepts_a_pdf_report_and_sends_it_as_a_file_content_block(): void
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
        ]);
        $doctor = $this->makeDoctor($company, Specialty::INTERNAL_MEDICINE);
        $client = $this->makeClient($company);
        $this->enrollClient($client, $doctor);
        Sanctum::actingAs($doctor);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['results' => []])]],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 10, 'total_tokens' => 110],
            ], 200),
        ]);

        $response = $this->postJson("/api/clients/{$client->id}/lab-results/analyze", [
            'report' => UploadedFile::fake()->create('panel-report.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();
            $userContent = collect($body['messages'])->firstWhere('role', 'user')['content'] ?? [];
            $fileBlock = collect($userContent)->firstWhere('type', 'file');

            return $fileBlock
                && $fileBlock['file']['filename'] === 'panel-report.pdf'
                && str_starts_with($fileBlock['file']['file_data'], 'data:application/pdf;base64,');
        });
    }
}
