<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureActiveClinicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_active_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/doctors')->assertOk();
    }

    public function test_a_deactivated_user_is_rejected_and_their_token_is_revoked(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/stats')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Your account has been deactivated. Please contact your clinic administrator.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_soft_deleted_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $user->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard/stats')
            ->assertStatus(401);
    }

    public function test_a_user_of_an_inactive_company_is_rejected_and_logged_out(): void
    {
        $company = Company::factory()->create(['status' => 'inactive']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Your clinic account has been deactivated. Please contact support.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_user_whose_company_has_no_active_subscription_is_rejected_and_logged_out(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Your clinic does not have an active subscription. Please contact your clinic administrator.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_project_admin_is_exempt_even_without_a_company(): void
    {
        $admin = User::factory()->create(['company_id' => null, 'is_project_admin' => true]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/doctors')->assertOk();
    }
}
