<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriptions_endpoint_exposes_ai_token_fields(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => 5000,
            'ai_tokens_used' => 1200,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();

        $response->assertJsonPath('data.0.max_ai_tokens', 5000)
            ->assertJsonPath('data.0.ai_tokens_used', 1200);
    }

    public function test_subscriptions_endpoint_exposes_null_max_ai_tokens_as_unlimited(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();

        $response->assertJsonPath('data.0.max_ai_tokens', null)
            ->assertJsonPath('data.0.ai_tokens_used', 0);
    }
}
