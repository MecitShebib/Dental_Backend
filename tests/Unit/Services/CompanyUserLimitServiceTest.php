<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CompanyUserLimitService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyUserLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function subscriptionFor(Company $company, string $specialtyKey, int $maxUsers): Subscription
    {
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();

        return Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => ucfirst($specialtyKey),
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => $maxUsers,
        ]);
    }

    public function test_the_max_users_limit_is_the_sum_of_every_active_specialty_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $this->subscriptionFor($company, Specialty::DENTAL, 2);
        $this->subscriptionFor($company, Specialty::GYNECOLOGY, 2);

        // 3 existing active users + 1 new one = 4, which is over either
        // subscription's own max_users (2) alone but within the pooled
        // total (2 + 2 = 4) -- confirms the two specialties share one cap.
        User::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'active']);

        app(CompanyUserLimitService::class)->assertCanHaveAnotherActiveUser($company);
        $this->assertTrue(true);
    }

    public function test_it_still_throws_once_the_pooled_total_is_reached(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $this->subscriptionFor($company, Specialty::DENTAL, 2);
        $this->subscriptionFor($company, Specialty::GYNECOLOGY, 2);

        User::factory()->count(4)->create(['company_id' => $company->id, 'status' => 'active']);

        $this->expectException(ValidationException::class);

        app(CompanyUserLimitService::class)->assertCanHaveAnotherActiveUser($company);
    }

    public function test_sync_active_users_writes_the_same_company_wide_count_to_every_active_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = $this->subscriptionFor($company, Specialty::DENTAL, 10);
        $gynecology = $this->subscriptionFor($company, Specialty::GYNECOLOGY, 10);
        User::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'active']);

        app(CompanyUserLimitService::class)->syncActiveUsers($company);

        $this->assertSame(3, $dental->fresh()->active_users);
        $this->assertSame(3, $gynecology->fresh()->active_users);
    }
}
