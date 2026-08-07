<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
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

    public function test_creating_a_company_automatically_seeds_its_treatment_catalog(): void
    {
        $response = $this->actingAs($this->adminUser())->post('/admin/companies', [
            'name' => 'New Clinic',
            'code' => 'NEW-01',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.companies.index'));

        $company = Company::query()->where('code', 'NEW-01')->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->get();

        $this->assertGreaterThanOrEqual(100, $items->count());
        $this->assertTrue($items->where('scope', TreatmentCatalog::SCOPE_COMPANY)->contains('code', 'consultation'));
        $this->assertTrue($items->where('scope', TreatmentCatalog::SCOPE_ODONTOGRAM)->contains('code', 'fillingMaterial:composite'));
    }

    public function test_show_page_lists_the_companys_users_for_a_project_admin_with_no_company(): void
    {
        // Regression test: Sanctum's package default (config/sanctum.php
        // 'guard' => ['web']) makes auth('sanctum') check the session-based
        // 'web' guard before the bearer token. Since the admin panel IS a
        // 'web'-guard session, that made BelongsToCompany's global scope
        // wrongly activate on admin-panel requests, scoping every query to
        // the logged-in admin's own company_id -- null for a project admin,
        // so the "Company Users" table came up empty for every company.
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Regression Staff']);

        $response = $this->actingAs($this->adminUser())->get(route('admin.companies.show', $company));

        $response->assertOk();
        $response->assertViewHas('users', function ($users) use ($staff) {
            return $users->contains('id', $staff->id);
        });
    }
}
