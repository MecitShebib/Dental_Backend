<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
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
            'password' => 'Password123!',
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
            'password' => 'Password123!',
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
            'password' => 'Password123!',
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
            'password' => 'Password123!',
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

    public function test_a_sole_system_managers_permissions_cannot_be_stripped_via_self_edit(): void
    {
        $company = Company::factory()->create();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $admin = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $admin->roles()->attach($role);
        $admin->permissions()->sync(Permission::query()->pluck('id')->all());
        Sanctum::actingAs($admin);

        // Shape of the payload the React app's own self-edit form sends
        // today (see UserEditPage.jsx/saveUserDraft): permission chips
        // unchecked, role_ids not resent -- the sole admin should come out
        // of this with every role and permission intact regardless.
        $response = $this->putJson("/api/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_doctor' => true,
            'specialty_id' => $dental->id,
            'role_ids' => [],
            'permission_ids' => [],
        ]);

        $response->assertOk();
        $admin->refresh();
        $this->assertTrue($admin->isSystemManager());
        $this->assertSame(Permission::count(), $admin->permissions()->count());
    }

    public function test_a_second_system_managers_permissions_can_still_be_freely_edited(): void
    {
        $company = Company::factory()->create();
        $firstManager = $this->makeManager($company);
        $secondManager = $this->makeManager($company);
        $secondManager->permissions()->sync(Permission::query()->pluck('id')->all());
        Sanctum::actingAs($firstManager);

        $response = $this->putJson("/api/users/{$secondManager->id}", [
            'name' => $secondManager->name,
            'email' => $secondManager->email,
            'permission_ids' => [],
        ]);

        $response->assertOk();
        $this->assertSame(0, $secondManager->fresh()->permissions()->count());
    }

    public function test_the_doctors_endpoint_includes_specialty_key(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'status' => 'active', 'specialty_id' => $gynecology->id]);

        $response = $this->getJson('/api/doctors');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('specialty_key');
        $this->assertTrue($keys->contains('gynecology'));
    }

    // Regression test: two users sharing one phone number (even across
    // different companies) made the OTP login lookup ambiguous for that
    // number -- phone had no uniqueness check at all, unlike email.
    public function test_a_user_cannot_be_created_with_a_phone_number_already_in_use(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        User::factory()->create(['company_id' => $company->id, 'phone' => '+905342641738']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/users', [
            'name' => 'Duplicate Phone',
            'email' => 'duplicate-phone@example.test',
            'password' => 'Password123!',
            'phone' => '+905342641738',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_a_phone_number_already_used_by_another_company_is_also_rejected(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $manager = $this->makeManager($companyB);
        User::factory()->create(['company_id' => $companyA->id, 'phone' => '+905342641738']);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/users', [
            'name' => 'Cross Company Duplicate',
            'email' => 'cross-company@example.test',
            'password' => 'Password123!',
            'phone' => '+905342641738',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_a_users_phone_can_be_updated_to_an_unused_number_but_not_to_a_taken_one(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $employee = User::factory()->create(['company_id' => $company->id, 'phone' => '+905340000001']);
        $otherEmployee = User::factory()->create(['company_id' => $company->id, 'phone' => '+905340000002']);
        Sanctum::actingAs($manager);

        // Keeping its own unchanged phone number must not self-block.
        $this->putJson("/api/users/{$employee->id}", [
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => '+905340000001',
        ])->assertOk();

        // Taking someone else's number is rejected.
        $this->putJson("/api/users/{$employee->id}", [
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => '+905340000002',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertSame('+905340000001', $employee->fresh()->phone);
    }
}
