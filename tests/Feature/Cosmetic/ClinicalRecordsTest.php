<?php

namespace Tests\Feature\Cosmetic;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        return Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();
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

        $this->getJson("/api/cosmetic/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/cosmetic/clients/{$client->id}/profile", ['fitzpatrick_skin_type' => 'I', 'aesthetic_goals' => 'Notes for aesthetic_goals', 'areas_of_interest' => ['forehead', 'glabella'], 'previous_procedures' => 'Notes for previous_procedures', 'allergies' => 'Notes for allergies', 'keloid_tendency' => true, 'skincare_products' => 'Notes for skincare_products', 'isotretinoin_use' => 'none', 'pregnancy_breastfeeding' => true])->assertOk();

        $this->assertDatabaseHas('cosmetic_client_profiles', ['client_id' => $client->id, 'fitzpatrick_skin_type' => 'I']);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/cosmetic/clients/{$client->id}/profile", ['fitzpatrick_skin_type' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fitzpatrick_skin_type');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/cosmetic/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/cosmetic/clients/{$client->id}/procedure-logs")->assertUnprocessable();
    }

    public function test_procedure_logs_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/cosmetic/clients/{$client->id}/procedure-logs", ['performed_at' => '2026-09-10', 'procedure_type' => 'Sample procedure_type', 'product_name' => 'Sample product_name', 'lot_number' => 'Sample lot_number', 'amount' => 1.5, 'unit' => 'units', 'treatment_area' => 'Sample treatment_area', 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/cosmetic/clients/{$client->id}/procedure-logs")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/cosmetic/procedure-logs/{$uuid}", ['performed_at' => '2026-09-11', 'procedure_type' => 'Sample procedure_type updated', 'product_name' => 'Sample product_name updated', 'lot_number' => 'Sample lot_number updated', 'amount' => 2.5, 'unit' => 'ml', 'treatment_area' => 'Sample treatment_area updated', 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.performed_at', '2026-09-11');

        $this->deleteJson("/api/cosmetic/procedure-logs/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('cosmetic_procedure_logs', ['uuid' => $uuid]);
    }

    public function test_procedure_logs_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/cosmetic/clients/{$client->id}/procedure-logs", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('performed_at');
    }

    public function test_procedure_logs_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/cosmetic/clients/{$client->id}/procedure-logs", ['performed_at' => '2026-09-10', 'procedure_type' => 'Sample procedure_type', 'product_name' => 'Sample product_name', 'lot_number' => 'Sample lot_number', 'amount' => 1.5, 'unit' => 'units', 'treatment_area' => 'Sample treatment_area', 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/cosmetic/procedure-logs/{$uuid}", ['performed_at' => '2026-09-11', 'procedure_type' => 'Sample procedure_type updated', 'product_name' => 'Sample product_name updated', 'lot_number' => 'Sample lot_number updated', 'amount' => 2.5, 'unit' => 'ml', 'treatment_area' => 'Sample treatment_area updated', 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_procedure_logs_before_photo_upload_is_served_through_a_signed_url(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $response = $this->post("/api/cosmetic/clients/{$client->id}/procedure-logs", [
            ...['performed_at' => '2026-09-10', 'procedure_type' => 'Sample procedure_type', 'product_name' => 'Sample product_name', 'lot_number' => 'Sample lot_number', 'amount' => 1.5, 'unit' => 'units', 'treatment_area' => 'Sample treatment_area', 'notes' => 'Notes for notes'],
            'before_photo' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $url = $response->json('data.before_photo_url');
        $this->assertNotNull($url);
        $this->get($url)->assertOk();
    }

    public function test_procedure_logs_after_photo_upload_is_served_through_a_signed_url(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $response = $this->post("/api/cosmetic/clients/{$client->id}/procedure-logs", [
            ...['performed_at' => '2026-09-10', 'procedure_type' => 'Sample procedure_type', 'product_name' => 'Sample product_name', 'lot_number' => 'Sample lot_number', 'amount' => 1.5, 'unit' => 'units', 'treatment_area' => 'Sample treatment_area', 'notes' => 'Notes for notes'],
            'after_photo' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $url = $response->json('data.after_photo_url');
        $this->assertNotNull($url);
        $this->get($url)->assertOk();
    }
}
