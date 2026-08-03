<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_companys_client(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        $otherClient = Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-9001',
            'name' => 'Other Co Client',
            'phone' => '+963900009001',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$otherClient->id}")->assertNotFound();
    }

    public function test_a_user_cannot_update_another_companys_client(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        $otherClient = Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-9002',
            'name' => 'Other Co Client',
            'phone' => '+963900009002',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/clients/{$otherClient->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->assertDatabaseHas('clients', ['id' => $otherClient->id, 'name' => 'Other Co Client']);
    }

    public function test_a_user_cannot_delete_another_companys_client(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        $otherClient = Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-9003',
            'name' => 'Other Co Client',
            'phone' => '+963900009003',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/clients/{$otherClient->id}")->assertNotFound();
        $this->assertDatabaseHas('clients', ['id' => $otherClient->id, 'deleted_at' => null]);
    }

    public function test_a_users_client_list_excludes_other_companies(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Client::create([
            'company_id' => $ownCompany->id,
            'client_code' => 'CL-9004',
            'name' => 'Own Co Client',
            'phone' => '+963900009004',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-9005',
            'name' => 'Other Co Client',
            'phone' => '+963900009005',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/clients')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Own Co Client'));
        $this->assertFalse($names->contains('Other Co Client'));
    }

    public function test_a_user_cannot_view_another_companys_user(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/users/{$otherUser->id}")->assertNotFound();
    }

    public function test_a_non_manager_cannot_update_another_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $coworker = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->putJson("/api/users/{$coworker->id}", ['name' => 'Hijacked'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
        $this->assertDatabaseMissing('users', ['id' => $coworker->id, 'name' => 'Hijacked']);
    }

    public function test_a_user_cannot_set_is_project_admin_on_themselves(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'is_project_admin' => false]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'is_project_admin' => true,
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_project_admin' => false]);
    }

    public function test_a_user_cannot_set_is_project_admin_when_creating_a_user(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/users', [
            'name' => 'New Hire',
            'email' => 'new-hire@example.com',
            'password' => 'password',
            'is_project_admin' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'id' => $response->json('data.id'),
            'company_id' => $company->id,
            'is_project_admin' => false,
        ]);
    }
}
