<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryAdvanceTest extends TestCase
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

    public function test_setting_an_employees_monthly_salary(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        Sanctum::actingAs($manager);

        $this->putJson("/api/payroll/employees/{$employee->id}/salary", [
            'monthly_salary' => 3000,
        ])->assertOk()->assertJsonPath('data.monthly_salary', 3000);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'monthly_salary' => 3000]);
    }

    public function test_setting_a_doctors_commission_percentage(): void
    {
        $manager = $this->makeManager();
        $doctor = User::factory()->create(['company_id' => $manager->company_id, 'is_doctor' => true]);
        Sanctum::actingAs($manager);

        $this->putJson("/api/payroll/employees/{$doctor->id}/salary", [
            'monthly_salary' => 3000,
            'commission_percentage' => 10,
        ])->assertOk()->assertJsonPath('data.commission_percentage', 10);

        $this->assertDatabaseHas('users', ['id' => $doctor->id, 'commission_percentage' => 10]);
    }

    public function test_commission_percentage_over_100_is_rejected(): void
    {
        $manager = $this->makeManager();
        $doctor = User::factory()->create(['company_id' => $manager->company_id, 'is_doctor' => true]);
        Sanctum::actingAs($manager);

        $this->putJson("/api/payroll/employees/{$doctor->id}/salary", [
            'monthly_salary' => 3000,
            'commission_percentage' => 150,
        ])->assertStatus(422)->assertJsonValidationErrors('commission_percentage');
    }

    public function test_a_salary_advance_debits_the_fund(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id, 'monthly_salary' => 3000]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 500,
            'advance_date' => '2026-08-01',
        ])->assertCreated()->assertJsonPath('data.settled', false);

        $this->getJson('/api/fund/summary')
            ->assertJsonPath('data.balance', -500)
            ->assertJsonPath('data.by_source.salary_advance', -500);
    }

    public function test_an_employee_cannot_be_given_an_advance_from_another_company(): void
    {
        $manager = $this->makeManager();
        $otherCompany = Company::factory()->create();
        $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $otherEmployee->id,
            'amount' => 500,
            'advance_date' => '2026-08-01',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_deleting_an_unsettled_advance_removes_it_from_the_fund(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        Sanctum::actingAs($manager);

        $advanceId = $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 500,
            'advance_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/payroll/salary-advances/{$advanceId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_a_regular_user_cannot_manage_payroll(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/payroll/employees')
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');
    }

    public function test_an_unsettled_advance_can_be_corrected(): void
    {
        $manager = $this->makeManager();
        $employee = User::factory()->create(['company_id' => $manager->company_id]);
        Sanctum::actingAs($manager);

        $advanceId = $this->postJson('/api/payroll/salary-advances', [
            'user_id' => $employee->id,
            'amount' => 500,
            'advance_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/payroll/salary-advances/{$advanceId}", ['amount' => 350])
            ->assertOk()
            ->assertJsonPath('data.amount', 350);

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -350);
    }

    public function test_a_settled_advance_can_no_longer_be_corrected(): void
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

        $this->putJson("/api/payroll/salary-advances/{$advanceId}", ['amount' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('salary_advance');
    }
}
