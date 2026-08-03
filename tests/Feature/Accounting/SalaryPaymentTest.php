<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryPaymentTest extends TestCase
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

    public function test_paying_a_full_salary_with_no_advances_debits_the_fund_the_full_amount(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 3000]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()
            ->assertJsonPath('data.base_salary', 3000)
            ->assertJsonPath('data.advances_total', 0)
            ->assertJsonPath('data.net_amount', 3000);

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -3000);
    }

    public function test_a_salary_payment_nets_off_outstanding_advances_so_the_fund_is_only_debited_once(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 3000]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 800,
            'advance_date' => '2026-08-10',
        ])->assertCreated();

        // Fund is down 800 from the advance alone.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -800);

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()
            ->assertJsonPath('data.advances_total', 800)
            ->assertJsonPath('data.net_amount', 2200);

        // Total fund impact for the month must equal the full 3000 salary,
        // not 3000 + 800 double-counted.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -3000);
    }

    public function test_the_settled_advance_can_no_longer_be_deleted(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 1000]);
        Sanctum::actingAs($manager);

        $advanceId = $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 200,
            'advance_date' => '2026-08-05',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated();

        $this->deleteJson("/api/payroll/salary-advances/{$advanceId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('salary_advance');
    }

    public function test_advances_exceeding_the_salary_zero_out_the_net_payment_without_posting_a_fund_transaction(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 500]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 700,
            'advance_date' => '2026-08-05',
        ])->assertCreated();

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()
            ->assertJsonPath('data.advances_total', 700)
            ->assertJsonPath('data.net_amount', 0);

        // Only the original 700 advance ever left the fund.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -700);
    }

    public function test_an_employee_cannot_be_paid_twice_for_the_same_period(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 1000]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated();

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertStatus(422)->assertJsonValidationErrors('period_month');
    }

    public function test_an_employee_without_a_defined_salary_cannot_be_paid(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_the_paid_date_of_a_salary_payment_can_be_corrected(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 1000]);
        Sanctum::actingAs($manager);

        $paymentId = $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/payroll/salary-payments/{$paymentId}", ['paid_at' => '2026-09-01'])
            ->assertOk()
            ->assertJsonPath('data.paid_at', '2026-09-01');

        $this->getJson('/api/fund/transactions?source_type=salary_payment')
            ->assertJsonPath('data.0.occurred_on', '2026-09-01');
    }

    public function test_deleting_a_salary_payment_reverses_the_fund_and_unsettles_its_advances(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 1000]);
        Sanctum::actingAs($manager);

        $advanceId = $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 300,
            'advance_date' => '2026-08-05',
        ])->assertCreated()->json('data.id');

        $paymentId = $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()->json('data.id');

        // Sanity check: total fund impact so far is the full 1000 salary.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -1000);

        $this->deleteJson("/api/payroll/salary-payments/{$paymentId}")->assertOk();

        // The 700 net-payment leg is reversed; only the original 300 advance remains.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -300);

        // The advance is unsettled again and can now be deleted (or reused by a future payment).
        $this->assertDatabaseHas('salary_advances', ['id' => $advanceId, 'settled_by_salary_payment_id' => null]);
        $this->deleteJson("/api/payroll/salary-advances/{$advanceId}")->assertOk();
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_a_regular_user_cannot_delete_a_salary_payment(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 1000]);
        Sanctum::actingAs($manager);

        $paymentId = $this->postJson('/api/payroll/salary-payments', [
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'paid_at' => '2026-08-31',
        ])->assertCreated()->json('data.id');

        $outsider = User::factory()->create(['company_id' => $manager->company_id]);
        Sanctum::actingAs($outsider);

        $this->deleteJson("/api/payroll/salary-payments/{$paymentId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }
}
