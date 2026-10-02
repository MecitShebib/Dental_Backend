<?php

namespace Tests\Feature\GeneralSurgery;

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
        return Specialty::query()->where('key', Specialty::GENERAL_SURGERY)->firstOrFail();
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

        $this->getJson("/api/general_surgery/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/general_surgery/clients/{$client->id}/profile", ['indication' => 'Notes for indication', 'planned_operation' => 'Sample planned_operation', 'asa_score' => 'I', 'previous_surgeries' => 'Notes for previous_surgeries', 'anticoagulant_use' => 'Notes for anticoagulant_use', 'allergies' => 'Notes for allergies', 'blood_type' => 'A', 'rh' => 'positive', 'preop_checklist' => ['cbc', 'coagulation']])->assertOk();

        $this->assertDatabaseHas('general_surgery_client_profiles', ['client_id' => $client->id, 'indication' => 'Notes for indication']);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/general_surgery/clients/{$client->id}/profile", ['asa_score' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asa_score');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/general_surgery/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/general_surgery/clients/{$client->id}/operations")->assertUnprocessable();
        $this->getJson("/api/general_surgery/clients/{$client->id}/followups")->assertUnprocessable();
    }

    public function test_operations_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/general_surgery/clients/{$client->id}/operations", ['operated_at' => '2026-09-10', 'operation_name' => 'Sample operation_name', 'duration_minutes' => 1, 'anesthesia_type' => 'general', 'surgeon_notes' => 'Notes for surgeon_notes', 'complications' => 'Notes for complications'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/general_surgery/clients/{$client->id}/operations")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/general_surgery/operations/{$uuid}", ['operated_at' => '2026-09-11', 'operation_name' => 'Sample operation_name updated', 'duration_minutes' => 2, 'anesthesia_type' => 'spinal', 'surgeon_notes' => 'Notes for surgeon_notes updated', 'complications' => 'Notes for complications updated'])
            ->assertOk()
            ->assertJsonPath('data.operated_at', '2026-09-11');

        $this->deleteJson("/api/general_surgery/operations/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('general_surgery_operations', ['uuid' => $uuid]);
    }

    public function test_operations_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/general_surgery/clients/{$client->id}/operations", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operated_at');
    }

    public function test_operations_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/general_surgery/clients/{$client->id}/operations", ['operated_at' => '2026-09-10', 'operation_name' => 'Sample operation_name', 'duration_minutes' => 1, 'anesthesia_type' => 'general', 'surgeon_notes' => 'Notes for surgeon_notes', 'complications' => 'Notes for complications'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/general_surgery/operations/{$uuid}", ['operated_at' => '2026-09-11', 'operation_name' => 'Sample operation_name updated', 'duration_minutes' => 2, 'anesthesia_type' => 'spinal', 'surgeon_notes' => 'Notes for surgeon_notes updated', 'complications' => 'Notes for complications updated'])->assertNotFound();
    }

    public function test_followups_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/general_surgery/clients/{$client->id}/followups", ['followup_date' => '2026-09-10', 'wound_status' => 'clean', 'drain_removed_at' => '2026-09-10', 'sutures_removed_at' => '2026-09-10', 'complications' => 'Notes for complications', 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/general_surgery/clients/{$client->id}/followups")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/general_surgery/followups/{$uuid}", ['followup_date' => '2026-09-11', 'wound_status' => 'serous', 'drain_removed_at' => '2026-09-11', 'sutures_removed_at' => '2026-09-11', 'complications' => 'Notes for complications updated', 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.followup_date', '2026-09-11');

        $this->deleteJson("/api/general_surgery/followups/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('general_surgery_followups', ['uuid' => $uuid]);
    }

    public function test_followups_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/general_surgery/clients/{$client->id}/followups", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('followup_date');
    }

    public function test_followups_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/general_surgery/clients/{$client->id}/followups", ['followup_date' => '2026-09-10', 'wound_status' => 'clean', 'drain_removed_at' => '2026-09-10', 'sutures_removed_at' => '2026-09-10', 'complications' => 'Notes for complications', 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/general_surgery/followups/{$uuid}", ['followup_date' => '2026-09-11', 'wound_status' => 'serous', 'drain_removed_at' => '2026-09-11', 'sutures_removed_at' => '2026-09-11', 'complications' => 'Notes for complications updated', 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_followups_rejects_a_operation_id_from_another_patient(): void
    {
        [$company, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $otherClient = $this->makeClient($company);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($otherClient, $doctor);
        $foreignId = $this->postJson("/api/general_surgery/clients/{$otherClient->id}/operations", ['operated_at' => '2026-09-10', 'operation_name' => 'Sample operation_name', 'duration_minutes' => 1, 'anesthesia_type' => 'general', 'surgeon_notes' => 'Notes for surgeon_notes', 'complications' => 'Notes for complications'])->json('data.id');

        $this->postJson("/api/general_surgery/clients/{$client->id}/followups", [...['followup_date' => '2026-09-10', 'wound_status' => 'clean', 'drain_removed_at' => '2026-09-10', 'sutures_removed_at' => '2026-09-10', 'complications' => 'Notes for complications', 'notes' => 'Notes for notes'], 'operation_id' => $foreignId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation_id');
    }
}
