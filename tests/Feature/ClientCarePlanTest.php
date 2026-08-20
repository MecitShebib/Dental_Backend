<?php

namespace Tests\Feature;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientCarePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_it_lists_a_clients_care_plans_across_specialties_newest_first(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-7001',
            'name' => 'Multi Specialty Patient',
            'phone' => '+963900007001',
            'status' => 'new',
        ]);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $orthopedics = Specialty::query()->where('key', Specialty::ORTHOPEDICS)->firstOrFail();

        $olderPlan = CarePlan::create([
            'company_id' => $doctor->company_id,
            'specialty_id' => $gynecology->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'created_by' => $doctor->id,
            'title' => 'Prenatal Care Plan',
            'status' => CarePlan::STATUS_CONFIRMED,
        ]);
        $olderPlan->created_at = now()->subDay();
        $olderPlan->save();

        CarePlan::create([
            'company_id' => $doctor->company_id,
            'specialty_id' => $orthopedics->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'created_by' => $doctor->id,
            'title' => 'Injury Rehab Plan',
            'status' => CarePlan::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/clients/{$client->id}/care-plans")->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertSame('Injury Rehab Plan', $response->json('data.0.title'));
        $this->assertSame(Specialty::ORTHOPEDICS, $response->json('data.0.specialty_key'));
        $this->assertSame('Prenatal Care Plan', $response->json('data.1.title'));
        $this->assertSame(Specialty::GYNECOLOGY, $response->json('data.1.specialty_key'));
    }

    public function test_it_is_scoped_to_the_clients_own_company(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        $otherClient = Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-7002',
            'name' => 'Other Co Client',
            'phone' => '+963900007002',
            'status' => 'new',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$otherClient->id}/care-plans")->assertNotFound();
    }
}
