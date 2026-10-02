<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\GynecologyClientProfile;
use App\Models\Specialty;
use App\Models\TreatmentCharge;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientTreatmentChargesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_the_service_log_lists_every_charge_for_a_client_newest_first(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);
        $client = $this->makeClient($company);

        $older = TreatmentCharge::create([
            'client_id' => $client->id, 'source_type' => 'visit', 'source_id' => 1,
            'amount' => 100, 'description' => 'Consultation', 'created_by' => $user->id,
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $newer = TreatmentCharge::create([
            'client_id' => $client->id, 'source_type' => 'inventory_sale', 'source_id' => 2,
            'amount' => 50, 'description' => 'Whitening Kit', 'created_by' => $user->id,
        ]);

        $response = $this->getJson("/api/clients/{$client->id}/treatment-charges")->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.amount', 50)
            ->assertJsonPath('data.0.source_type', 'inventory_sale')
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_the_service_log_is_scoped_to_the_companys_own_clients(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherClient = $this->makeClient($otherCompany);
        TreatmentCharge::create([
            'client_id' => $otherClient->id, 'source_type' => 'manual', 'amount' => 100, 'description' => 'X',
        ]);

        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$otherClient->id}/treatment-charges")->assertNotFound();
    }

    public function test_message_variables_resolves_the_clients_own_specialty_profile_and_primary_doctor(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create(['name' => 'Doctovaria Clinic']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);
        $client = $this->makeClient($company);
        $client->update(['name' => 'Jane Patient']);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'name' => 'Dr. Ada', 'specialty_id' => $gynecology->id]);
        ClientSpecialtyRecord::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'specialty_id' => $gynecology->id, 'primary_doctor_id' => $doctor->id,
        ]);
        GynecologyClientProfile::create(['client_id' => $client->id, 'blood_type' => 'A']);

        $response = $this->getJson("/api/clients/{$client->id}/message-variables?specialty=gynecology")->assertOk();

        $variables = $response->json('data');
        $this->assertSame('Jane Patient', $variables['client_name']);
        $this->assertSame('Dr. Ada', $variables['doctor_name']);
        $this->assertSame('Doctovaria Clinic', $variables['company_name']);
        $this->assertSame('A', $variables['patient.blood_type']);
        $this->assertArrayHasKey('date', $variables);
        $this->assertArrayHasKey('time', $variables);
    }

    public function test_message_variables_is_scoped_to_the_companys_own_clients(): void
    {
        $otherCompany = Company::factory()->create();
        $otherClient = $this->makeClient($otherCompany);

        $ownCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$otherClient->id}/message-variables")->assertNotFound();
    }
}
