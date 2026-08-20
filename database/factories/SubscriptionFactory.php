<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Every subscription created in a test is realistically dental
            // (the only specialty that actually exists) -- null, not an
            // error, in a test that hasn't seeded Specialty rows at all,
            // since the column is nullable.
            'specialty_id' => Specialty::query()->where('key', Specialty::DENTAL)->value('id'),
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'max_users' => 50,
            'active_users' => 0,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
            'price' => 0,
            'notes' => null,
        ];
    }
}
