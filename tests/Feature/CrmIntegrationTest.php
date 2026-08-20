<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CrmIntegration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function fakeZoho(): void
    {
        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response(['access_token' => 'zoho-access-token', 'expires_in' => 3600], 200),
            'https://www.zohoapis.com/crm/v3/Contacts' => Http::response(['data' => [['status' => 'success']]], 200),
        ]);
    }

    public function test_a_company_admin_can_connect_crm_credentials(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->getJson('/api/settings/crm')->assertOk()->assertJsonPath('data', null);

        $response = $this->putJson('/api/settings/crm', [
            'client_id' => '1000.ABCDEF',
            'client_secret' => 'super-secret',
            'refresh_token' => 'refresh-token-value',
        ])->assertOk();

        $response->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.provider', 'zoho')
            ->assertJsonPath('data.client_id', '1000.ABCDEF');

        $this->assertDatabaseHas('crm_integrations', ['company_id' => $company->id, 'client_id' => '1000.ABCDEF']);
    }

    public function test_crm_settings_are_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/settings/crm')->assertStatus(422);
    }

    public function test_disconnecting_removes_the_integration(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->putJson('/api/settings/crm', ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c'])->assertOk();
        $this->deleteJson('/api/settings/crm')->assertOk();

        $this->assertDatabaseMissing('crm_integrations', ['company_id' => $company->id]);
    }

    public function test_the_test_endpoint_verifies_the_oauth_refresh_works(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);
        $this->fakeZoho();

        $this->putJson('/api/settings/crm', ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c'])->assertOk();

        $this->postJson('/api/settings/crm/test')->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://accounts.zoho.com/oauth/v2/token'
            && $request['refresh_token'] === 'c');
    }

    public function test_creating_a_client_automatically_pushes_it_to_zoho_as_a_contact(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);
        $this->fakeZoho();

        CrmIntegration::create([
            'company_id' => $company->id,
            'client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c',
            'status' => 'active', 'connected_at' => now(),
        ]);

        $this->postJson('/api/clients', [
            'name' => 'Jane Doe',
            'phone' => '+905551234567',
            'gender' => 'female',
        ])->assertCreated();

        Http::assertSent(fn ($request) => $request->url() === 'https://www.zohoapis.com/crm/v3/Contacts'
            && $request['data'][0]['First_Name'] === 'Jane'
            && $request['data'][0]['Last_Name'] === 'Doe'
            && $request->hasHeader('Authorization', 'Zoho-oauthtoken zoho-access-token'));
    }

    public function test_a_client_is_not_pushed_to_crm_when_no_integration_is_connected(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);
        Http::fake();

        $this->postJson('/api/clients', [
            'name' => 'No Crm Patient',
            'phone' => '+905550001111',
            'gender' => 'male',
        ])->assertCreated();

        Http::assertNothingSent();
    }
}
