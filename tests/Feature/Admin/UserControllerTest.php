<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
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

    public function test_creating_a_user_from_the_company_page_succeeds(): void
    {
        // Regression test: StoreUserRequest didn't validate company_id at
        // all, so $data['company_id'] in Admin\UserController::store() hit
        // an undefined-array-key warning that Laravel escalates to a fatal
        // ErrorException -- a 500 on every "Create User" submission from the
        // admin panel's company page, even though the form does send it.
        $company = Company::factory()->create();

        $response = $this->actingAs($this->adminUser())->post('/admin/users', [
            'company_id' => $company->id,
            'name' => 'New Staffer',
            'email' => 'new-staffer@example.com',
            'password' => 'password123',
            'status' => 'active',
            'is_doctor' => '0',
        ]);

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertDatabaseHas('users', [
            'email' => 'new-staffer@example.com',
            'company_id' => $company->id,
        ]);
    }
}
