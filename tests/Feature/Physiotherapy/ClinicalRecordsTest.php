<?php

namespace Tests\Feature\Physiotherapy;

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
        return Specialty::query()->where('key', Specialty::PHYSIOTHERAPY)->firstOrFail();
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

        $this->getJson("/api/physiotherapy/clients/{$client->id}/profile")->assertOk();

        $this->putJson("/api/physiotherapy/clients/{$client->id}/profile", ['diagnosis' => 'Notes for diagnosis', 'referring_physician' => 'Sample referring_physician', 'affected_region' => 'shoulder', 'side' => 'right', 'prescribed_session_count' => 1, 'home_exercise_program' => 'Notes for home_exercise_program'])->assertOk();

        $this->assertDatabaseHas('physiotherapy_client_profiles', ['client_id' => $client->id, 'diagnosis' => 'Notes for diagnosis']);
    }

    public function test_the_profile_rejects_an_unknown_option(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->putJson("/api/physiotherapy/clients/{$client->id}/profile", ['affected_region' => 'not-a-real-option'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('affected_region');
    }

    public function test_another_doctor_of_the_same_specialty_cannot_read_the_patient(): void
    {
        [$company, , $client] = $this->ownedClient();
        $other = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $this->specialty()->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/physiotherapy/clients/{$client->id}/profile")->assertUnprocessable();
        $this->getJson("/api/physiotherapy/clients/{$client->id}/sessions")->assertUnprocessable();
    }

    public function test_sessions_can_be_created_listed_updated_and_deleted(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $created = $this->postJson("/api/physiotherapy/clients/{$client->id}/sessions", ['session_date' => '2026-09-10', 'session_number' => 2, 'pain_vas' => 1, 'rom_measurements' => [['joint' => 'Sample joint', 'movement' => 'Sample movement', 'degrees' => 1]], 'muscle_strength' => [['muscle' => 'Sample muscle', 'mmt' => 1]], 'modalities' => ['tens', 'therapeutic_ultrasound'], 'notes' => 'Notes for notes'])->assertCreated();
        $uuid = $created->json('data.uuid');

        $this->getJson("/api/physiotherapy/clients/{$client->id}/sessions")->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/physiotherapy/sessions/{$uuid}", ['session_date' => '2026-09-11', 'session_number' => 3, 'pain_vas' => 2, 'rom_measurements' => [['joint' => 'Sample joint updated', 'movement' => 'Sample movement updated', 'degrees' => 2]], 'muscle_strength' => [['muscle' => 'Sample muscle updated', 'mmt' => 2]], 'modalities' => ['tens', 'therapeutic_ultrasound'], 'notes' => 'Notes for notes updated'])
            ->assertOk()
            ->assertJsonPath('data.session_date', '2026-09-11');

        $this->deleteJson("/api/physiotherapy/sessions/{$uuid}")->assertOk();
        $this->assertDatabaseMissing('physiotherapy_sessions', ['uuid' => $uuid]);
    }

    public function test_sessions_requires_a_date(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);

        $this->postJson("/api/physiotherapy/clients/{$client->id}/sessions", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session_date');
    }

    public function test_sessions_of_another_company_is_not_found(): void
    {
        [, $doctor, $client] = $this->ownedClient();
        Sanctum::actingAs($doctor);
        $uuid = $this->postJson("/api/physiotherapy/clients/{$client->id}/sessions", ['session_date' => '2026-09-10', 'session_number' => 2, 'pain_vas' => 1, 'rom_measurements' => [['joint' => 'Sample joint', 'movement' => 'Sample movement', 'degrees' => 1]], 'muscle_strength' => [['muscle' => 'Sample muscle', 'mmt' => 1]], 'modalities' => ['tens', 'therapeutic_ultrasound'], 'notes' => 'Notes for notes'])->json('data.uuid');

        $outsider = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        Sanctum::actingAs($outsider);

        $this->putJson("/api/physiotherapy/sessions/{$uuid}", ['session_date' => '2026-09-11', 'session_number' => 3, 'pain_vas' => 2, 'rom_measurements' => [['joint' => 'Sample joint updated', 'movement' => 'Sample movement updated', 'degrees' => 2]], 'muscle_strength' => [['muscle' => 'Sample muscle updated', 'mmt' => 2]], 'modalities' => ['tens', 'therapeutic_ultrasound'], 'notes' => 'Notes for notes updated'])->assertNotFound();
    }
}
