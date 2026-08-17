<?php

namespace Tests\Feature\Gynecology;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
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

    public function test_index_only_returns_gynecology_patients_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynPatient = $this->makeClient($company, 'Gyn Patient');
        $dentalPatient = $this->makeClient($company, 'Dental Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($gynPatient, $gynecology, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($dentalPatient, $dental, $manager);

        Sanctum::actingAs($manager);

        // Deliberately NOT passing ?specialty=gynecology -- the route itself
        // must force it.
        $response = $this->getJson('/api/gynecology/clients');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Gyn Patient'));
        $this->assertFalse($names->contains('Dental Patient'));
    }

    public function test_store_enrolls_the_new_patient_as_gynecology_without_a_specialty_id_in_the_payload(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/gynecology/clients', [
            'name' => 'New Gyn Patient',
            'phone' => '+15551234567',
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $client = Client::where('name', 'New Gyn Patient')->firstOrFail();
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::GYNECOLOGY)->value('id'),
        ]);
    }

    public function test_a_gynecology_doctor_can_create_and_see_their_own_patient_through_this_endpoint(): void
    {
        $company = Company::factory()->create();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        Sanctum::actingAs($doctor);

        $createResponse = $this->postJson('/api/gynecology/clients', [
            'name' => 'Doctor Created Patient',
            'phone' => '+15559876543',
            'gender' => 'female',
        ]);
        $createResponse->assertCreated();
        $client = Client::where('name', 'Doctor Created Patient')->firstOrFail();
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => $gynecology->id,
            'primary_doctor_id' => $doctor->id,
        ]);

        $indexResponse = $this->getJson('/api/gynecology/clients');
        $indexResponse->assertOk();
        $this->assertTrue(collect($indexResponse->json('data'))->pluck('name')->contains('Doctor Created Patient'));
    }
}
