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
}
