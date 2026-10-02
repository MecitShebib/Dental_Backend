<?php

namespace Tests\Feature\Hematology;

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
        return Specialty::query()->where('key', Specialty::HEMATOLOGY)->firstOrFail();
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

        $this->getJson("/api/hematology/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/hematology/clients/{$client->id}/profile", ['blood_type' => 'A', 'rh' => 'positive', 'primary_diagnosis' => 'iron_deficiency_anemia', 'diagnosis_notes' => 'Notes for diagnosis_notes', 'anticoagulant' => 'none', 'inr_target_min' => 1.5, 'inr_target_max' => 1.5, 'splenectomy' => true, 'chemo_protocol' => 'Notes for chemo_protocol', 'chemo_cycles_planned' => 1, 'chemo_cycles_completed' => 1, 'bleeding_history' => 'Notes for bleeding_history'])->assertOk();

        $this->assertDatabaseHas('hematology_client_profiles', ['client_id' => $client->id, 'blood_type' => 'A']);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/hematology/clients/{$client->id}/profile", ['blood_type' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('blood_type');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/hematology/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/hematology/clients/{$client->id}/blood-counts")->assertUnprocessable();
        $this->getJson("/api/hematology/clients/{$client->id}/transfusions")->assertUnprocessable();
    }

    public function test_blood_counts_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/hematology/clients/{$client->id}/blood-counts", ['measured_at' => '2026-09-10', 'hb' => 1.5, 'hct' => 1.5, 'wbc' => 1.5, 'plt' => 1, 'mcv' => 1.5, 'ferritin' => 1.5, 'inr' => 1.5, 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/hematology/clients/{$client->id}/blood-counts")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/hematology/blood-counts/{$uuid}", ['measured_at' => '2026-09-11', 'hb' => 2.5, 'hct' => 2.5, 'wbc' => 2.5, 'plt' => 2, 'mcv' => 2.5, 'ferritin' => 2.5, 'inr' => 2.5, 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.measured_at', '2026-09-11');

        $this->deleteJson("/api/hematology/blood-counts/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('hematology_blood_counts', ['uuid' => $uuid]);
    }

    public function test_blood_counts_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/hematology/clients/{$client->id}/blood-counts", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('measured_at');
    }

    public function test_blood_counts_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/hematology/clients/{$client->id}/blood-counts", ['measured_at' => '2026-09-10', 'hb' => 1.5, 'hct' => 1.5, 'wbc' => 1.5, 'plt' => 1, 'mcv' => 1.5, 'ferritin' => 1.5, 'inr' => 1.5, 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/hematology/blood-counts/{$uuid}", ['measured_at' => '2026-09-11', 'hb' => 2.5, 'hct' => 2.5, 'wbc' => 2.5, 'plt' => 2, 'mcv' => 2.5, 'ferritin' => 2.5, 'inr' => 2.5, 'notes' => 'Notes for notes updated'])->assertNotFound();
    }

    public function test_transfusions_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/hematology/clients/{$client->id}/transfusions", ['transfused_at' => '2026-09-10', 'product' => 'prbc', 'units' => 2, 'reaction' => true, 'reaction_notes' => 'Notes for reaction_notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/hematology/clients/{$client->id}/transfusions")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/hematology/transfusions/{$uuid}", ['transfused_at' => '2026-09-11', 'product' => 'ffp', 'units' => 3, 'reaction' => false, 'reaction_notes' => 'Notes for reaction_notes updated'])
            ->assertOk()
            ->assertJsonPath('data.transfused_at', '2026-09-11');

        $this->deleteJson("/api/hematology/transfusions/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('hematology_transfusions', ['uuid' => $uuid]);
    }

    public function test_transfusions_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/hematology/clients/{$client->id}/transfusions", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transfused_at');
    }

    public function test_transfusions_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/hematology/clients/{$client->id}/transfusions", ['transfused_at' => '2026-09-10', 'product' => 'prbc', 'units' => 2, 'reaction' => true, 'reaction_notes' => 'Notes for reaction_notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/hematology/transfusions/{$uuid}", ['transfused_at' => '2026-09-11', 'product' => 'ffp', 'units' => 3, 'reaction' => false, 'reaction_notes' => 'Notes for reaction_notes updated'])->assertNotFound();
    }
}
