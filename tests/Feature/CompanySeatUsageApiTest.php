<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Users page's "Max users" / "Remaining seats" cards read
 * GET /api/companies/{id}'s seat_usage, which must be the same pooled,
 * company-wide numbers CompanyUserLimitService enforces (a company can hold
 * one active subscription per specialty; limits add up across them).
 */
class CompanySeatUsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_details_include_pooled_seat_usage_across_every_active_subscription(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        foreach ([Specialty::DENTAL, Specialty::PEDIATRICS] as $key) {
            Subscription::create([
                'company_id' => $company->id,
                'specialty_id' => Specialty::query()->where('key', $key)->value('id'),
                'plan_name' => 'Plan '.$key,
                'status' => 'active',
                'starts_at' => now()->subDay()->toDateString(),
                'max_doctors' => 2,
            ]);
        }
        $manager = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        User::factory()->create(['company_id' => $company->id, 'status' => 'active', 'is_doctor' => true]);
        User::factory()->create(['company_id' => $company->id, 'status' => 'inactive']);
        Sanctum::actingAs($manager);

        $this->getJson("/api/companies/{$company->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.seat_usage.users')
            ->assertJsonPath('data.seat_usage.doctors.limit', 4)
            ->assertJsonPath('data.seat_usage.doctors.used', 1)
            ->assertJsonPath('data.seat_usage.assistants.limit', null)
            ->assertJsonPath('data.seat_usage.assistants.used', 1);
    }
}
