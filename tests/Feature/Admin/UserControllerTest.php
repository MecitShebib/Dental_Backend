<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'is_project_admin' => true,
            'status' => 'active',
        ]);
    }

    private function branchFor(Company $company): Branch
    {
        return Branch::create(['company_id' => $company->id, 'name' => 'Main Branch', 'status' => 'active']);
    }

    public function test_creating_a_user_from_the_company_page_succeeds(): void
    {
        // Regression test: StoreUserRequest didn't validate company_id at
        // all, so $data['company_id'] in Admin\UserController::store() hit
        // an undefined-array-key warning that Laravel escalates to a fatal
        // ErrorException -- a 500 on every "Create User" submission from the
        // admin panel's company page, even though the form does send it.
        $company = Company::factory()->create();
        $branch = $this->branchFor($company);

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'New Staffer',
            'email' => 'new-staffer@example.com',
            'password' => 'Password123!',
            'status' => 'active',
            'is_doctor' => '0',
        ]);

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertDatabaseHas('users', [
            'email' => 'new-staffer@example.com',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_creating_a_user_from_the_company_page_requires_a_branch(): void
    {
        $company = Company::factory()->create();
        $this->branchFor($company);

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'name' => 'Branchless Staffer',
            'email' => 'branchless-staffer@example.com',
            'password' => 'Password123!',
            'status' => 'active',
            'is_doctor' => '0',
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['email' => 'branchless-staffer@example.com']);
    }

    public function test_creating_a_user_cannot_be_assigned_another_companys_branch(): void
    {
        // Regression test: branch_id validation used to scope against the
        // *acting admin's* own company_id (null for a project admin), not
        // the target company being created for -- which happened to also
        // block cross-company branches, but for the wrong reason (it
        // rejected every branch_id, including valid ones for this company).
        // This confirms the fix scopes correctly rather than having flipped
        // to accepting anything.
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherBranch = $this->branchFor($otherCompany);

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Cross Company Staffer',
            'email' => 'cross-company-staffer@example.com',
            'password' => 'Password123!',
            'status' => 'active',
            'is_doctor' => '0',
        ]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseMissing('users', ['email' => 'cross-company-staffer@example.com']);
    }

    public function test_creating_a_user_from_the_company_page_can_assign_access_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $branch = $this->branchFor($company);
        $manageClients = Permission::query()->where('slug', 'manage_clients')->firstOrFail();
        $manageAppointments = Permission::query()->where('slug', 'manage_appointments')->firstOrFail();

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Permissioned Staffer',
            'email' => 'permissioned-staffer@example.com',
            'password' => 'Password123!',
            'status' => 'active',
            'is_doctor' => '0',
            'permission_ids' => [$manageClients->id, $manageAppointments->id],
        ]);

        $response->assertRedirect(route('admin.companies.show', $company));
        $user = User::query()->where('email', 'permissioned-staffer@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$manageClients->id, $manageAppointments->id],
            $user->permissions->pluck('id')->all(),
        );
    }

    public function test_updating_a_user_from_the_company_page_can_change_access_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $branch = $this->branchFor($company);
        $manageUsers = Permission::query()->where('slug', 'manage_users')->firstOrFail();
        $manageAccounting = Permission::query()->where('slug', 'manage_accounting')->firstOrFail();

        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $user->permissions()->sync([$manageUsers->id]);

        $response = $this->actingAs($this->adminUser())->put("/admin/users/{$user->id}", [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => 'active',
            'is_doctor' => '0',
            'permission_ids' => [$manageAccounting->id],
        ]);

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertEqualsCanonicalizing(
            [$manageAccounting->id],
            $user->fresh()->permissions->pluck('id')->all(),
        );
    }

    public function test_a_malformed_phone_number_is_rejected_on_create(): void
    {
        $company = Company::factory()->create();
        $branch = $this->branchFor($company);

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Bad Phone Staffer',
            'email' => 'bad-phone-staffer@example.com',
            'phone' => 'call-me-maybe',
            'password' => 'Password123!',
            'status' => 'active',
            'is_doctor' => '0',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('users', ['email' => 'bad-phone-staffer@example.com']);
    }

    public function test_a_failed_update_scopes_its_errors_to_that_users_own_modal_id(): void
    {
        $company = Company::factory()->create();
        $branch = $this->branchFor($company);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);

        $response = $this->actingAs($this->adminUser())->from(route('admin.companies.show', $company))->put(
            "/admin/users/{$user->id}",
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => '',
                'email' => 'not-an-email',
                'status' => 'active',
                'is_doctor' => '0',
                '_modal_id' => 'update-user-'.$user->id,
            ],
        );

        $response->assertSessionHasErrors(['name', 'email'], null, 'update-user-'.$user->id);
        $response->assertSessionHasInput('_modal_id', 'update-user-'.$user->id);
    }
}
