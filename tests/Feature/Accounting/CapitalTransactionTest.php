<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapitalTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_capital_injection_credits_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $this->postJson('/api/capital-transactions', [
            'type' => 'injection',
            'amount' => 5000,
            'party_name' => 'Dr. Owner',
            'transaction_date' => '2026-08-01',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')
            ->assertJsonPath('data.balance', 5000)
            ->assertJsonPath('data.by_source.capital', 5000);
    }

    public function test_a_withdrawal_debits_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $this->postJson('/api/capital-transactions', [
            'type' => 'injection',
            'amount' => 5000,
            'transaction_date' => '2026-08-01',
        ])->assertCreated();

        $this->postJson('/api/capital-transactions', [
            'type' => 'withdrawal',
            'amount' => 1200,
            'party_name' => 'Dr. Owner',
            'transaction_date' => '2026-08-02',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 3800);
    }

    public function test_updating_a_capital_transaction_amount_adjusts_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $id = $this->postJson('/api/capital-transactions', [
            'type' => 'injection',
            'amount' => 1000,
            'transaction_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/capital-transactions/{$id}", ['amount' => 1500])->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 1500);
    }

    public function test_deleting_a_capital_transaction_removes_it_from_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $id = $this->postJson('/api/capital-transactions', [
            'type' => 'injection',
            'amount' => 1000,
            'transaction_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/capital-transactions/{$id}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_a_regular_user_cannot_record_capital_transactions(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/capital-transactions', [
            'type' => 'injection',
            'amount' => 100,
            'transaction_date' => '2026-08-01',
        ])->assertStatus(422)->assertJsonValidationErrors('user');
    }
}
