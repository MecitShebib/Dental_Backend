<?php

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Services\CompanyBranchLimitService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyBranchLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function subscriptionFor(Company $company, string $specialtyKey, int $maxBranches): Subscription
    {
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();

        return Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => ucfirst($specialtyKey),
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_branches' => $maxBranches,
        ]);
    }

    public function test_the_max_branches_limit_is_the_sum_of_every_active_specialty_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $this->subscriptionFor($company, Specialty::DENTAL, 1);
        $this->subscriptionFor($company, Specialty::GYNECOLOGY, 1);

        Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);

        // 1 existing + 1 new = 2, over either subscription's own
        // max_branches (1) alone but within the pooled total (1 + 1 = 2).
        app(CompanyBranchLimitService::class)->assertCanHaveAnotherBranch($company);
        $this->assertTrue(true);
    }

    public function test_it_still_throws_once_the_pooled_total_is_reached(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $this->subscriptionFor($company, Specialty::DENTAL, 1);
        $this->subscriptionFor($company, Specialty::GYNECOLOGY, 1);

        Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);

        $this->expectException(ValidationException::class);

        app(CompanyBranchLimitService::class)->assertCanHaveAnotherBranch($company);
    }
}
