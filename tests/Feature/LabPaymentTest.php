<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    protected function makeLabCase(Company $company, User $doctor, Client $client, float $labCost = 1000): int
    {
        return $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => $labCost,
        ])->assertCreated()->json('data.id');
    }

    public function test_a_full_payment_posts_an_expense_and_zeroes_the_remaining_balance(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->makeLabCase($company, $doctor, $client, 1000);

        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 1000,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')
            ->assertJsonPath('data.balance', -1000)
            ->assertJsonPath('data.by_source.expense', -1000);

        $this->getJson('/api/expenses')
            ->assertJsonPath('data.0.category', 'lab_fees')
            ->assertJsonPath('data.0.amount', 1000);

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertJsonPath('data.0.total_paid', 1000)
            ->assertJsonPath('data.0.remaining_balance', 0);
    }

    public function test_a_partial_payment_leaves_the_remaining_balance_owed_to_the_lab(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->makeLabCase($company, $doctor, $client, 10000);

        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 5000,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -5000);

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertJsonPath('data.0.lab_cost', 10000)
            ->assertJsonPath('data.0.total_paid', 5000)
            ->assertJsonPath('data.0.remaining_balance', 5000);

        // A second installment finishes paying it off.
        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 5000,
            'payment_date' => '2026-08-10',
            'payment_method' => 'bank_transfer',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -10000);
        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertJsonPath('data.0.remaining_balance', 0);
    }

    public function test_a_payment_exceeding_the_remaining_balance_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->makeLabCase($company, $doctor, $client, 1000);

        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 1500,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_deleting_a_payment_reverses_its_expense_and_fund_impact(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->makeLabCase($company, $doctor, $client, 1000);

        $paymentId = $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 400,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/lab-payments/{$paymentId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
        $this->getJson('/api/expenses')->assertJsonCount(0, 'data');
        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertJsonPath('data.0.remaining_balance', 1000);
    }

    public function test_deleting_a_lab_case_reverses_every_payments_expense_and_fund_impact(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->makeLabCase($company, $doctor, $client, 1000);

        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 300,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated();
        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 200,
            'payment_date' => '2026-08-05',
            'payment_method' => 'card',
        ])->assertCreated();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -500);

        $this->deleteJson("/api/lab-cases/{$labCaseId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
        $this->getJson('/api/expenses')->assertJsonCount(0, 'data');
    }

    public function test_lab_payments_are_scoped_to_the_companys_own_lab_cases(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $otherManager = $this->makeManager($otherCompany);
        $otherDoctor = User::factory()->create(['company_id' => $otherCompany->id, 'is_doctor' => true]);
        $otherClient = $this->makeClient($otherCompany);
        Sanctum::actingAs($otherManager);
        $otherLabCaseId = $this->makeLabCase($otherCompany, $otherDoctor, $otherClient, 500);
        $this->postJson("/api/lab-cases/{$otherLabCaseId}/payments", [
            'amount' => 200,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated();

        $ownUser = $this->makeManager($ownCompany);
        Sanctum::actingAs($ownUser);

        $this->getJson("/api/lab-cases/{$otherLabCaseId}/payments")->assertNotFound();
    }

    public function test_a_lab_partners_cari_ledger_gets_an_invoice_on_case_creation_and_a_credit_per_payment(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labPartnerId = $this->postJson('/api/lab-partners', ['name' => 'Cari Lab Partner'])->assertCreated()->json('data.id');

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'lab_partner_id' => $labPartnerId,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => 1000,
        ])->assertCreated()->json('data.id');

        $summary = collect($this->getJson("/api/cari/transactions/summary?partyable_type=lab_partner&partyable_id={$labPartnerId}")->assertOk()->json('data'))
            ->keyBy('currency');
        $this->assertEquals(1000.0, $summary['TRY']['debit']);
        $this->assertEquals(0.0, $summary['TRY']['credit']);

        $this->postJson("/api/lab-cases/{$labCaseId}/payments", [
            'amount' => 400,
            'payment_date' => '2026-08-04',
            'payment_method' => 'cash',
        ])->assertCreated();

        $summary = collect($this->getJson("/api/cari/transactions/summary?partyable_type=lab_partner&partyable_id={$labPartnerId}")->assertOk()->json('data'))
            ->keyBy('currency');
        $this->assertEquals(1000.0, $summary['TRY']['debit']);
        $this->assertEquals(400.0, $summary['TRY']['credit']);
        $this->assertEquals(600.0, $summary['TRY']['balance']);

        $this->deleteJson("/api/lab-cases/{$labCaseId}")->assertOk();

        $summary = collect($this->getJson("/api/cari/transactions/summary?partyable_type=lab_partner&partyable_id={$labPartnerId}")->assertOk()->json('data'))
            ->keyBy('currency');
        $this->assertEquals(0.0, $summary['TRY']['debit']);
        $this->assertEquals(0.0, $summary['TRY']['credit']);
    }
}
