<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum::actingAs() bypasses the real token-lookup guard callback, so it
 * would never catch a bug where a global scope on User (used to resolve the
 * acting user for other scopes) recurses back into resolving the acting
 * user. These tests authenticate with a real bearer token instead, to
 * exercise the actual guard resolution path.
 */
class RealBearerTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_bearer_token_can_authenticate_and_fetch_the_current_user(): void
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_a_real_bearer_token_can_list_scoped_resources(): void
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/clients')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/doctors')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/stats?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk();
    }
}
