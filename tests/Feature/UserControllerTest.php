<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    public function test_a_user_can_be_created_with_a_branch_assigned_via_branch_id(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Downtown Branch', 'status' => 'active']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/users', [
            'name' => 'New Staffer',
            'email' => 'staffer@example.test',
            'password' => 'password123',
            'branch_id' => $branch->id,
        ])->assertCreated();

        $response->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.branch_name', 'Downtown Branch');

        $this->assertDatabaseHas('users', ['email' => 'staffer@example.test', 'branch_id' => $branch->id]);
    }

    public function test_a_users_branch_can_be_changed_via_branch_id(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);
        $employee = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branchA->id]);
        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/users/{$employee->id}", [
            'name' => $employee->name,
            'email' => $employee->email,
            'branch_id' => $branchB->id,
        ])->assertOk();

        $response->assertJsonPath('data.branch_id', $branchB->id)
            ->assertJsonPath('data.branch_name', 'Branch B');
    }

    public function test_a_branch_from_another_company_cannot_be_assigned(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = $this->makeManager($ownCompany);
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Branch', 'status' => 'active']);
        Sanctum::actingAs($manager);

        $this->postJson('/api/users', [
            'name' => 'New Staffer',
            'email' => 'staffer2@example.test',
            'password' => 'password123',
            'branch_id' => $otherBranch->id,
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_a_user_with_no_branch_assigned_returns_the_legacy_free_text_branch_name(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $legacyUser = User::factory()->create(['company_id' => $company->id, 'branch_id' => null, 'branch_name' => 'Old Free-Text Branch']);
        Sanctum::actingAs($manager);

        $this->getJson("/api/users/{$legacyUser->id}")
            ->assertOk()
            ->assertJsonPath('data.branch_id', null)
            ->assertJsonPath('data.branch_name', 'Old Free-Text Branch');
    }

    public function test_a_doctor_can_be_created_with_a_specialty_id(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        $response = $this->postJson('/api/users', [
            'name' => 'Dr. Gyn',
            'email' => 'dr.gyn@example.com',
            'password' => 'secret123',
            'is_doctor' => true,
            'specialty_id' => $gynecology->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'dr.gyn@example.com',
            'specialty_id' => $gynecology->id,
        ]);
    }

    public function test_a_doctor_is_required_to_have_a_specialty_id(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/users', [
            'name' => 'Dr. No Specialty',
            'email' => 'dr.nospecialty@example.com',
            'password' => 'secret123',
            'is_doctor' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('specialty_id');
    }

    public function test_a_doctors_specialty_id_can_be_changed_via_update(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);

        $response = $this->putJson("/api/users/{$doctor->id}", [
            'is_doctor' => true,
            'specialty_id' => $gynecology->id,
        ]);

        $response->assertOk();
        $this->assertSame($gynecology->id, $doctor->fresh()->specialty_id);
    }
}
