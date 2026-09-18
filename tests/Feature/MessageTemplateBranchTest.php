<?php

namespace Tests\Feature;

use App\Enums\ClientLanguage;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\MessageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTemplateBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_branch_specific_override_can_be_saved_and_is_scoped_to_that_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Downtown Branch']);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/settings/message-templates', [
            'branch_id' => $branch->id,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Branch-specific reminder text.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.branch_id', $branch->id);

        $this->assertDatabaseHas('message_templates', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'key' => 'appointment_reminder',
            'body' => 'Branch-specific reminder text.',
        ]);

        // Fetching the company-wide view (no branch_id) should not surface the branch-specific row.
        $companyWide = $this->getJson('/api/settings/message-templates');
        $companyWide->assertOk();
        $companyWideRow = collect($companyWide->json('data'))
            ->firstWhere(fn ($row) => $row['key'] === 'appointment_reminder' && $row['channel'] === 'sms' && $row['language'] === 'en');
        $this->assertFalse($companyWideRow['is_custom']);

        // Fetching the branch-scoped view should surface it.
        $branchScoped = $this->getJson('/api/settings/message-templates?branch_id='.$branch->id);
        $branchScoped->assertOk();
        $branchScopedRow = collect($branchScoped->json('data'))
            ->firstWhere(fn ($row) => $row['key'] === 'appointment_reminder' && $row['channel'] === 'sms' && $row['language'] === 'en');
        $this->assertTrue($branchScopedRow['is_custom']);
        $this->assertSame('Branch-specific reminder text.', $branchScopedRow['body']);
    }

    public function test_a_branch_from_another_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);

        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Branch']);

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/settings/message-templates', [
            'branch_id' => $otherBranch->id,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Should fail.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['branch_id']);
    }

    public function test_render_falls_back_from_branch_specific_to_company_wide_to_default(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Downtown Branch']);
        $otherBranch = Branch::create(['company_id' => $company->id, 'name' => 'Uptown Branch']);

        $service = app(MessageTemplateService::class);

        // No override anywhere yet -- falls back to the hardcoded default.
        $default = $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $branch->id);
        $this->assertNotEmpty($default['body']);

        // Company-wide override (no branch_id) applies to every branch.
        \App\Models\MessageTemplate::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Company-wide text.',
        ]);

        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $branch->id)['body']);
        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $otherBranch->id)['body']);

        // A branch-specific override wins for that branch only.
        \App\Models\MessageTemplate::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Downtown-only text.',
        ]);

        $this->assertSame('Downtown-only text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $branch->id)['body']);
        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $otherBranch->id)['body']);
    }
}
