<?php

namespace Database\Factories;

use App\Models\Company;
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
