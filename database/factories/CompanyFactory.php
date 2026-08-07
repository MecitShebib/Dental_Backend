<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('CMP###??')),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => 'active',
            'notes' => null,
        ];
    }

    /**
     * A factory-created company represents a normal, working tenant by
     * default -- which means an active subscription, since that's what
     * EnsureActiveClinicAccess and SubscriptionAccessService require for
     * its users to do anything. Tests exercising the "no subscription"
     * edge case opt out with ->withoutSubscription() or delete it after.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Company $company) {
            Subscription::factory()->create(['company_id' => $company->id]);
        });
    }
}
