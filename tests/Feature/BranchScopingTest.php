<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Branch-level scoping mirrors specialty-level scoping: a doctor with their
 * own branch_id set is hard-scoped to it (ClientQueryService/
 * AppointmentQueryService/DashboardStatsService), while a non-doctor is
 * scoped only by an explicit branch_id filter if one is given. Records that
 * predate branch scoping (branch_id still null) stay visible from every
 * branch rather than silently disappearing.
 */
class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function makeClient(Company $company, ?int $branchId = null): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'branch_id' => $branchId,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient '.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    // -- Patients (Clients) --------------------------------------------------

    public function test_a_non_doctor_can_filter_patients_by_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B']);
        $this->makeClient($company, $branchA->id);
        $this->makeClient($company, $branchB->id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/clients?branch_id='.$branchA->id)->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_patient_with_no_branch_assigned_is_visible_from_every_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A']);
        $this->makeClient($company, null);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/clients?branch_id='.$branchA->id)->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_doctor_with_a_branch_is_hard_locked_to_it_regardless_of_the_branch_id_param(): void
    {
        $company = Company::factory()->create();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B']);

        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'branch_id' => $branchA->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::DENTAL)->value('id'),
        ]);

        $ownClient = $this->makeClient($company, $branchA->id);
        \App\Models\ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $ownClient->id,
            'specialty_id' => $doctor->specialty_id,
            'primary_doctor_id' => $doctor->id,
        ]);

        Sanctum::actingAs($doctor);

        // Asking for branch B is ignored -- the doctor only ever sees branch A.
        $response = $this->getJson('/api/clients?branch_id='.$branchB->id)->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownClient->id);
    }

    // -- Appointments ----------------------------------------------------

    public function test_a_doctor_with_a_branch_only_sees_their_own_branchs_appointments(): void
    {
        $company = Company::factory()->create();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B']);

        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'branch_id' => $branchA->id,
        ]);

        $clientA = $this->makeClient($company, $branchA->id);
        $clientB = $this->makeClient($company, $branchB->id);

        Appointment::create([
            'company_id' => $company->id,
            'client_id' => $clientA->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-09-20',
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
        Appointment::create([
            'company_id' => $company->id,
            'client_id' => $clientB->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-09-20',
            'start_time' => '11:00:00',
            'duration_minutes' => 30,
        ]);

        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/appointments')->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // -- Users -------------------------------------------------------------

    public function test_users_can_be_filtered_by_branch_and_unassigned_users_still_show(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B']);

        User::factory()->create(['company_id' => $company->id, 'branch_id' => $branchA->id]);
        User::factory()->create(['company_id' => $company->id, 'branch_id' => $branchB->id]);
        User::factory()->create(['company_id' => $company->id, 'branch_id' => null]);

        Sanctum::actingAs($manager);

        // The manager itself (created with no branch_id) + branch A user + the unassigned user.
        $response = $this->getJson('/api/users?branch_id='.$branchA->id)->assertOk();
        $response->assertJsonCount(3, 'data');
    }
}
