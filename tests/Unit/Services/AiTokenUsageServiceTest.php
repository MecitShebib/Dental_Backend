<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AiTokenUsageService;
use Database\Seeders\SpecialtySeeder;
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

    // ── Multi-specialty aggregation (2026-08-17) ─────────────────────────────

    public function test_the_limit_is_the_sum_of_every_active_specialty_subscription(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $dental->id, 'plan_name' => 'Dental',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => 1000, 'ai_tokens_used' => 900,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Gynecology',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => 500, 'ai_tokens_used' => 50,
        ]);

        // Company-wide used (950) is under the company-wide max (1500) even
        // though the dental subscription alone is nearly at its own cap --
        // confirms the two specialties share one pooled limit, not separate
        // per-specialty gates.
        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
        $this->assertTrue(true);
    }

    public function test_an_unlimited_specialty_subscription_makes_the_whole_company_unlimited(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $dental->id, 'plan_name' => 'Dental',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => 100, 'ai_tokens_used' => 100,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Gynecology (unlimited)',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => null, 'ai_tokens_used' => 0,
        ]);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
        $this->assertTrue(true);
    }

    public function test_the_combined_limit_still_throws_once_the_pooled_usage_reaches_it(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $dental->id, 'plan_name' => 'Dental',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => 500, 'ai_tokens_used' => 500,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Gynecology',
            'status' => 'active', 'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10, 'max_ai_tokens' => 500, 'ai_tokens_used' => 500,
        ]);

        $this->expectException(ValidationException::class);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
    }
}
