<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SharedDocumentController;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Role;
use App\Models\SharedDocument;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use App\Support\WhatsAppPhone;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientDocumentsAndAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function specialty(string $key): Specialty
    {
        return Specialty::query()->where('key', $key)->firstOrFail();
    }

    protected function manager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $manager->roles()->attach(Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']));

        return $manager;
    }

    protected function doctor(Company $company, string $specialty = 'dental'): User
    {
        return User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty($specialty)->id, 'status' => 'active']);
    }

    protected function client(Company $company, ?User $owner = null, string $phone = '05551234567'): Client
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Ayşe Yılmaz',
            'phone' => $phone,
            'gender' => 'female',
            'status' => 'new',
        ]);

        if ($owner) {
            ClientSpecialtyRecord::create(['company_id' => $company->id, 'client_id' => $client->id, 'specialty_id' => $owner->specialty_id, 'primary_doctor_id' => $owner->id, 'created_by' => $owner->id]);
        }

        return $client;
    }

    // --- Send document via WhatsApp (link, stored 30 days) --------------

    protected function pdf(string $name = 'Ayşe Yılmaz — Tedavi Planı.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%fake\n");
    }

    protected function share(array $overrides = []): array
    {
        return ['uuid' => (string) Str::uuid(), 'title' => 'Tedavi planı', 'file' => $this->pdf(), ...$overrides];
    }

    public function test_document_is_stored_for_a_month_and_served_at_its_link(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $doctor = $this->doctor($company);
        $client = $this->client($company, $doctor);
        Sanctum::actingAs($doctor);
        $payload = $this->share(['client_id' => $client->id]);

        $response = $this->post('/api/shared-documents', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.sent', false)
            ->assertJsonPath('data.phone', '905551234567');

        $this->assertSame(route('shared-documents.file', $payload['uuid']), $response->json('data.url'));
        $document = SharedDocument::query()->firstOrFail();
        $this->assertTrue($document->expires_at->between(now()->addDays(29), now()->addDays(31)));
        Storage::disk('local')->assertExists($document->path);

        $this->get("/d/{$payload['uuid']}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_expired_documents_are_deleted_with_their_file(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        Storage::disk('local')->put('shared-documents/old.pdf', 'x');
        $document = SharedDocument::create([
            'uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'title' => 'Old', 'filename' => 'old.pdf',
            'path' => 'shared-documents/old.pdf', 'expires_at' => now()->subDay(),
        ]);

        $this->get("/d/{$document->uuid}")->assertNotFound();
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing('shared-documents/old.pdf');

        Storage::disk('local')->put('shared-documents/old2.pdf', 'x');
        SharedDocument::create([
            'uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'title' => 'Old', 'filename' => 'old.pdf',
            'path' => 'shared-documents/old2.pdf', 'expires_at' => now()->subDay(),
        ]);
        $this->artisan('documents:purge-expired')->assertSuccessful();
        $this->assertSame(0, SharedDocument::query()->count());
        Storage::disk('local')->assertMissing('shared-documents/old2.pdf');
    }

    public function test_with_the_business_api_the_pdf_is_also_sent_as_a_document(): void
    {
        Storage::fake('local');
        config(['services.whatsapp.graph_base_url' => 'https://graph.test/v20.0']);
        Http::fake([
            'graph.test/v20.0/PHONE-ID/media' => Http::response(['id' => 'MEDIA-1']),
            'graph.test/v20.0/PHONE-ID/messages' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);
        $company = Company::factory()->create();
        WhatsAppIntegration::create(['company_id' => $company->id, 'access_token' => 'token', 'phone_number_id' => 'PHONE-ID', 'status' => 'active']);
        $doctor = $this->doctor($company);
        $client = $this->client($company, $doctor);
        Sanctum::actingAs($doctor);

        $this->post('/api/shared-documents', $this->share(['client_id' => $client->id, 'send_via_api' => '1']), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.sent', true);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'document'
            && $request['document']['filename'] === 'Ayşe Yılmaz — Tedavi Planı.pdf');
    }

    public function test_a_doctor_cannot_share_another_doctors_patient_or_another_companys(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $doctor = $this->doctor($company);
        $colleague = $this->doctor($company);
        $colleaguesPatient = $this->client($company, $colleague);
        $otherCompanyPatient = $this->client(Company::factory()->create());
        Sanctum::actingAs($doctor);

        $this->post('/api/shared-documents', $this->share(['client_id' => $colleaguesPatient->id]), ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/shared-documents', $this->share(['client_id' => $otherCompanyPatient->id]), ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_only_pdfs_and_fresh_uuids_are_accepted(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $doctor = $this->doctor($company);
        $client = $this->client($company, $doctor);
        Sanctum::actingAs($doctor);

        $this->post('/api/shared-documents', $this->share(['client_id' => $client->id, 'file' => UploadedFile::fake()->create('x.html', 1, 'text/html')]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $payload = $this->share(['client_id' => $client->id]);
        $this->post('/api/shared-documents', $payload, ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/shared-documents', [...$payload, 'file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('uuid');
    }

    public function test_whatsapp_document_filename_keeps_the_patient_name(): void
    {
        $this->assertSame('Ayşe Yılmaz — Reçete.pdf', SharedDocumentController::filename('Rx', 'Ayşe Yılmaz — Reçete.pdf'));
        $this->assertSame('INV_0001 — Ayşe.pdf', SharedDocumentController::filename('Rx', 'INV/0001 — Ayşe.pdf'));
        $this->assertSame('Tedavi-planı.pdf', SharedDocumentController::filename('Tedavi planı', 'blob'));
    }

    public function test_whatsapp_phone_normalization(): void
    {
        $this->assertSame('905551234567', WhatsAppPhone::normalize('0555 123 45 67'));
        $this->assertSame('963900009001', WhatsAppPhone::normalize('+963 900 009 001'));
        $this->assertSame('4915112345678', WhatsAppPhone::normalize('0049 151 12345678'));
        $this->assertNull(WhatsAppPhone::normalize(''));
    }

    // --- Assign a patient to a doctor -----------------------------------

    public function test_manager_can_reassign_a_patient_to_another_doctor_of_the_specialty(): void
    {
        $company = Company::factory()->create();
        $manager = $this->manager($company);
        $first = $this->doctor($company);
        $second = $this->doctor($company);
        $client = $this->client($company, $first);
        Sanctum::actingAs($manager);

        $this->getJson("/api/clients/{$client->id}/assigned-doctor?specialty=dental")
            ->assertOk()->assertJsonPath('data.doctor_id', $first->id);

        $this->putJson("/api/clients/{$client->id}/assigned-doctor", ['specialty' => 'dental', 'doctor_id' => $second->id])
            ->assertOk()->assertJsonPath('data.doctor_id', $second->id)->assertJsonPath('data.doctor_name', $second->name);

        // The new doctor now owns the patient; the old one no longer does.
        Sanctum::actingAs($second);
        $this->getJson("/api/clients/{$client->id}")->assertOk();
        Sanctum::actingAs($first);
        $this->getJson("/api/clients/{$client->id}")->assertStatus(422);
    }

    public function test_manager_can_assign_a_patient_with_no_record_yet(): void
    {
        $company = Company::factory()->create();
        $manager = $this->manager($company);
        $gynecologist = $this->doctor($company, 'gynecology');
        $client = $this->client($company);
        Sanctum::actingAs($manager);

        $this->putJson("/api/clients/{$client->id}/assigned-doctor", ['specialty' => 'gynecology', 'doctor_id' => $gynecologist->id])->assertOk();

        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id, 'specialty_id' => $gynecologist->specialty_id, 'primary_doctor_id' => $gynecologist->id,
        ]);
    }

    public function test_assignment_rejects_doctors_of_another_specialty_or_company(): void
    {
        $company = Company::factory()->create();
        $manager = $this->manager($company);
        $client = $this->client($company);
        $gynecologist = $this->doctor($company, 'gynecology');
        $foreignDentist = $this->doctor(Company::factory()->create());
        Sanctum::actingAs($manager);

        foreach ([$gynecologist, $foreignDentist] as $doctor) {
            $this->putJson("/api/clients/{$client->id}/assigned-doctor", ['specialty' => 'dental', 'doctor_id' => $doctor->id])
                ->assertStatus(422)->assertJsonValidationErrors('doctor_id');
        }
    }

    public function test_non_managers_cannot_view_or_change_the_assignment(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->doctor($company);
        $client = $this->client($company, $doctor);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/clients/{$client->id}/assigned-doctor?specialty=dental")->assertForbidden();
        $this->putJson("/api/clients/{$client->id}/assigned-doctor", ['specialty' => 'dental', 'doctor_id' => $doctor->id])->assertForbidden();
    }

    // --- Nutrition diet / exercise plans --------------------------------

    public function test_dietitian_can_add_and_edit_diet_and_exercise_plans(): void
    {
        $company = Company::factory()->create();
        $dietitian = $this->doctor($company, 'nutrition');
        $client = $this->client($company, $dietitian);
        Sanctum::actingAs($dietitian);

        $diet = $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'diet', 'title' => 'Week 1', 'body' => 'Breakfast: oats',
        ])->assertCreated()->assertJsonPath('data.diet_plan', 'Breakfast: oats')->assertJsonPath('data.specialty_key', 'nutrition');

        $this->postJson("/api/nutrition/clients/{$client->id}/diet-exercise-plans", [
            'kind' => 'exercise', 'body' => '30 min walk',
        ])->assertCreated()->assertJsonPath('data.exercise_plan', '30 min walk');

        $this->putJson('/api/nutrition/diet-exercise-plans/'.$diet->json('data.uuid'), [
            'kind' => 'diet', 'title' => 'Week 1 (revised)', 'body' => 'Breakfast: eggs',
        ])->assertOk()->assertJsonPath('data.diet_plan', 'Breakfast: eggs')->assertJsonPath('data.title', 'Week 1 (revised)');

        $this->getJson("/api/clients/{$client->id}/care-plans")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_manager_adding_a_plan_uses_the_clients_assigned_dietitian(): void
    {
        $company = Company::factory()->create();
        $manager = $this->manager($company);
        $dietitian = $this->doctor($company, 'nutrition');
        $assigned = $this->client($company, $dietitian);
        $unassigned = $this->client($company);
        Sanctum::actingAs($manager);

        $this->postJson("/api/nutrition/clients/{$assigned->id}/diet-exercise-plans", ['kind' => 'diet', 'body' => 'x'])
            ->assertCreated()->assertJsonPath('data.doctor_id', $dietitian->id);

        $this->postJson("/api/nutrition/clients/{$unassigned->id}/diet-exercise-plans", ['kind' => 'diet', 'body' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('doctor_id');
    }

    public function test_another_dietitian_cannot_edit_a_plan(): void
    {
        $company = Company::factory()->create();
        $owner = $this->doctor($company, 'nutrition');
        $other = $this->doctor($company, 'nutrition');
        $client = $this->client($company, $owner);
        $plan = CarePlan::create([
            'company_id' => $company->id, 'specialty_id' => $owner->specialty_id, 'client_id' => $client->id,
            'doctor_id' => $owner->id, 'created_by' => $owner->id, 'title' => 'x', 'diet_plan' => 'x', 'status' => 'confirmed',
        ]);
        Sanctum::actingAs($other);

        $this->putJson("/api/nutrition/diet-exercise-plans/{$plan->uuid}", ['kind' => 'diet', 'body' => 'hijacked'])->assertStatus(422);
        $this->assertSame('x', $plan->fresh()->diet_plan);
    }
}
