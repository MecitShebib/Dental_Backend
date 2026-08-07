<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AiTokenUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiTokenUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function activeSubscription(Company $company, ?int $maxAiTokens, int $aiTokensUsed = 0): Subscription
    {
        // Company::factory() auto-creates a default subscription; replace it
        // rather than stacking a second one that currentSubscription() could
        // pick instead of this test's specifically-configured one.
        $company->subscriptions()->delete();

        return Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => $maxAiTokens,
            'ai_tokens_used' => $aiTokensUsed,
        ]);
    }

    public function test_assert_can_use_ai_tokens_passes_when_unlimited(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, null, 999999);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);

        $this->assertTrue(true);
    }

    public function test_assert_can_use_ai_tokens_passes_when_under_the_limit(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, 1000, 500);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);

        $this->assertTrue(true);
    }

    public function test_assert_can_use_ai_tokens_throws_when_at_the_limit(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, 1000, 1000);

        $this->expectException(ValidationException::class);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
    }

    public function test_assert_can_use_ai_tokens_throws_when_there_is_no_active_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();

        $this->expectException(ValidationException::class);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
    }

    public function test_record_usage_increments_the_subscription_counter_and_creates_a_log_row(): void
    {
        $company = Company::factory()->create();
        $subscription = $this->activeSubscription($company, 1000, 100);
        $user = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = Client::create([
            'client_code' => 'CL-9001',
            'name' => 'Test Client',
            'phone' => '+963900009001',
            'gender' => 'male',
            'status' => 'new',
        ]);

        app(AiTokenUsageService::class)->recordUsage(
            $company,
            $user,
            $client,
            'ai_treatment_plan_preview',
            'gpt-4o-mini',
            120,
            80
        );

        $this->assertSame(300, $subscription->fresh()->ai_tokens_used);
        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_preview',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
        ]);
    }
}
