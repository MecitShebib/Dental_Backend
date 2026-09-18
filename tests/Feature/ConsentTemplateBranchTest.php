<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ConsentTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentTemplateBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_consent_template_can_be_created_with_a_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Downtown Branch']);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/consent-templates', [
            'branch_id' => $branch->id,
            'title' => 'Branch-specific Consent',
            'body' => 'Consent body text.',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.branch_id', $branch->id);
        $response->assertJsonPath('data.branch_name', 'Downtown Branch');
        $this->assertDatabaseHas('consent_templates', ['title' => 'Branch-specific Consent', 'branch_id' => $branch->id]);
    }

    public function test_a_consent_template_can_be_created_without_a_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/consent-templates', [
            'title' => 'Company-wide Consent',
            'body' => 'Consent body text.',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.branch_id', null);
    }

    public function test_a_branch_from_another_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);

        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Branch']);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/consent-templates', [
            'branch_id' => $otherBranch->id,
            'title' => 'Should Fail',
            'body' => 'Consent body text.',
            'language' => 'en',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['branch_id']);
    }

    public function test_a_consent_templates_branch_can_be_updated(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'New Branch']);
        $template = ConsentTemplate::create([
            'company_id' => $company->id,
            'title' => 'Existing',
            'body' => 'Body',
            'language' => 'en',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/consent-templates/{$template->id}", [
            'branch_id' => $branch->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.branch_id', $branch->id);
    }
}
