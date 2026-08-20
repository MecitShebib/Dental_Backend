<?php

namespace Tests\Feature\InternalMedicine;

use App\Models\Client;
use App\Models\Company;
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

    public function test_index_only_returns_internal_medicine_patients_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);

        $internalMedicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $medPatient = $this->makeClient($company, 'Med Patient');
        $dentalPatient = $this->makeClient($company, 'Dental Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($medPatient, $internalMedicine, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($dentalPatient, $dental, $manager);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/internal_medicine/clients');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Med Patient'));
        $this->assertFalse($names->contains('Dental Patient'));
    }

    public function test_store_enrolls_the_new_patient_as_internal_medicine_without_a_specialty_id_in_the_payload(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/internal_medicine/clients', [
            'name' => 'New Med Patient',
            'phone' => '+15551230000',
            'gender' => 'male',
        ]);

        $response->assertCreated();
        $client = Client::where('name', 'New Med Patient')->firstOrFail();
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->value('id'),
        ]);
    }
}
