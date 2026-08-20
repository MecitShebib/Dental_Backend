<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\LabPartner;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use App\Models\TreatmentCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(Company $company, string $name, ?Branch $branch = null): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_patient_debts_report_lists_only_clients_with_a_positive_balance_highest_first(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        Sanctum::actingAs($user);

        $paidUp = $this->makeClient($company, 'Paid Up Patient');
        TreatmentCharge::create(['client_id' => $paidUp->id, 'source_type' => 'manual', 'amount' => 500, 'description' => 'Filling']);
        Payment::create(['client_id' => $paidUp->id, 'payment_date' => '2026-08-01', 'amount' => 500, 'payment_method' => 'cash']);

        $smallDebt = $this->makeClient($company, 'Small Debt Patient');
        TreatmentCharge::create(['client_id' => $smallDebt->id, 'source_type' => 'manual', 'amount' => 500, 'description' => 'Filling']);
        Payment::create(['client_id' => $smallDebt->id, 'payment_date' => '2026-08-01', 'amount' => 300, 'payment_method' => 'cash']);

        $bigDebt = $this->makeClient($company, 'Big Debt Patient');
        TreatmentCharge::create(['client_id' => $bigDebt->id, 'source_type' => 'manual', 'amount' => 2000, 'description' => 'Crown']);

        $response = $this->getJson('/api/reports/patient-debts')->assertOk()->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.client_name', 'Big Debt Patient')
            ->assertJsonPath('data.0.remaining_amount', 2000)
            ->assertJsonPath('data.1.client_name', 'Small Debt Patient')
            ->assertJsonPath('data.1.remaining_amount', 200);
    }

    public function test_patient_debts_report_is_scoped_to_the_companys_own_clients(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $otherClient = $this->makeClient($otherCompany, 'Other Company Patient');
        TreatmentCharge::create(['client_id' => $otherClient->id, 'source_type' => 'manual', 'amount' => 1000, 'description' => 'Crown']);

        $ownUser = $this->makeManager($ownCompany);
        Sanctum::actingAs($ownUser);

        $this->getJson('/api/reports/patient-debts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_patient_debts_report_is_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/reports/patient-debts')->assertStatus(422);
    }

    public function test_patient_debts_report_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        Sanctum::actingAs($user);

        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);

        $clientA = $this->makeClient($company, 'Branch A Patient', $branchA);
        TreatmentCharge::create(['client_id' => $clientA->id, 'source_type' => 'manual', 'amount' => 1000, 'description' => 'Crown']);

        $clientB = $this->makeClient($company, 'Branch B Patient', $branchB);
        TreatmentCharge::create(['client_id' => $clientB->id, 'source_type' => 'manual', 'amount' => 2000, 'description' => 'Crown']);

        $this->getJson("/api/reports/patient-debts?branch_id={$branchA->id}")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Branch A Patient');

        $this->getJson('/api/reports/patient-debts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_patient_debts_report_can_be_filtered_by_doctor_and_specialty(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $this->seed(\Database\Seeders\SpecialtySeeder::class);
        $dental = \App\Models\Specialty::query()->where('key', \App\Models\Specialty::DENTAL)->firstOrFail();
        $gynecology = \App\Models\Specialty::query()->where('key', \App\Models\Specialty::GYNECOLOGY)->firstOrFail();
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $gynDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        Sanctum::actingAs($user);

        $dentalPatient = $this->makeClient($company, 'Dental Patient');
        TreatmentCharge::create(['client_id' => $dentalPatient->id, 'source_type' => 'manual', 'amount' => 1000, 'description' => 'Crown']);
        \App\Models\ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $dentalPatient->id,
            'specialty_id' => $dental->id,
            'primary_doctor_id' => $dentalDoctor->id,
        ]);

        $gynPatient = $this->makeClient($company, 'Gynecology Patient');
        TreatmentCharge::create(['client_id' => $gynPatient->id, 'source_type' => 'manual', 'amount' => 2000, 'description' => 'Checkup']);
        \App\Models\ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $gynPatient->id,
            'specialty_id' => $gynecology->id,
            'primary_doctor_id' => $gynDoctor->id,
        ]);

        $this->getJson("/api/reports/patient-debts?doctor_id={$dentalDoctor->id}")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Dental Patient');

        $this->getJson('/api/reports/patient-debts?specialty=gynecology')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Gynecology Patient');
    }

    public function test_lab_debts_report_lists_only_lab_partners_with_a_positive_balance_highest_first(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company, 'Test Patient');
        Sanctum::actingAs($user);

        $paidUpLab = LabPartner::create(['company_id' => $company->id, 'name' => 'Paid Up Lab', 'is_active' => true]);
        $owedLab = LabPartner::create(['company_id' => $company->id, 'name' => 'Owed Lab', 'is_active' => true]);
        $bigOwedLab = LabPartner::create(['company_id' => $company->id, 'name' => 'Big Owed Lab', 'is_active' => true]);

        $paidCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id, 'lab_partner_id' => $paidUpLab->id, 'work_type' => 'crown', 'sent_date' => '2026-08-01', 'lab_cost' => 500,
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/lab-cases/{$paidCaseId}/payments", ['amount' => 500, 'payment_date' => '2026-08-02', 'payment_method' => 'cash'])->assertCreated();

        $owedCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id, 'lab_partner_id' => $owedLab->id, 'work_type' => 'bridge', 'sent_date' => '2026-08-01', 'lab_cost' => 1000,
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/lab-cases/{$owedCaseId}/payments", ['amount' => 400, 'payment_date' => '2026-08-02', 'payment_method' => 'cash'])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id, 'lab_partner_id' => $bigOwedLab->id, 'work_type' => 'veneer', 'sent_date' => '2026-08-01', 'lab_cost' => 3000,
        ])->assertCreated();

        $response = $this->getJson('/api/reports/lab-debts')->assertOk()->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.lab_partner_name', 'Big Owed Lab')
            ->assertJsonPath('data.0.remaining_balance', 3000)
            ->assertJsonPath('data.1.lab_partner_name', 'Owed Lab')
            ->assertJsonPath('data.1.remaining_balance', 600);
    }

    public function test_lab_debts_report_is_scoped_to_the_companys_own_lab_partners(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $otherDoctor = User::factory()->create(['company_id' => $otherCompany->id, 'is_doctor' => true]);
        $otherClient = $this->makeClient($otherCompany, 'Other Company Patient');
        $otherLab = LabPartner::create(['company_id' => $otherCompany->id, 'name' => 'Other Company Lab', 'is_active' => true]);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        Sanctum::actingAs($otherUser);

        $this->postJson("/api/clients/{$otherClient->id}/lab-cases", [
            'doctor_id' => $otherDoctor->id, 'lab_partner_id' => $otherLab->id, 'work_type' => 'crown', 'sent_date' => '2026-08-01', 'lab_cost' => 1000,
        ])->assertCreated();

        $ownUser = $this->makeManager($ownCompany);
        Sanctum::actingAs($ownUser);

        $this->getJson('/api/reports/lab-debts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_lab_debts_report_is_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/reports/lab-debts')->assertStatus(422);
    }

    public function test_lab_debts_report_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($user);

        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);
        $clientA = $this->makeClient($company, 'Branch A Patient', $branchA);
        $clientB = $this->makeClient($company, 'Branch B Patient', $branchB);

        $labA = LabPartner::create(['company_id' => $company->id, 'name' => 'Lab A', 'is_active' => true]);
        $labB = LabPartner::create(['company_id' => $company->id, 'name' => 'Lab B', 'is_active' => true]);

        $this->postJson("/api/clients/{$clientA->id}/lab-cases", [
            'doctor_id' => $doctor->id, 'lab_partner_id' => $labA->id, 'work_type' => 'crown', 'sent_date' => '2026-08-01', 'lab_cost' => 1000,
        ])->assertCreated();
        $this->postJson("/api/clients/{$clientB->id}/lab-cases", [
            'doctor_id' => $doctor->id, 'lab_partner_id' => $labB->id, 'work_type' => 'crown', 'sent_date' => '2026-08-01', 'lab_cost' => 2000,
        ])->assertCreated();

        $this->getJson("/api/reports/lab-debts?branch_id={$branchA->id}")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lab_partner_name', 'Lab A');

        $this->getJson('/api/reports/lab-debts')->assertOk()->assertJsonCount(2, 'data');
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_payroll_summary_reports_this_periods_pay_and_outstanding_advances_per_employee(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'monthly_salary' => 5000,
            'commission_percentage' => 10,
        ]);
        Sanctum::actingAs($manager);

        SalaryPayment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'period_year' => 2026,
            'period_month' => 8,
            'base_salary' => 5000,
            'treatment_revenue' => 2000,
            'commission_percentage' => 10,
            'commission_amount' => 200,
            'advances_total' => 0,
            'net_amount' => 5200,
            'paid_at' => '2026-08-05',
        ]);

        SalaryAdvance::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'amount' => 300,
            'advance_date' => '2026-08-08',
        ]);

        $response = $this->getJson('/api/reports/payroll-summary?year=2026&month=8')->assertOk();

        $rows = collect($response->json('data.employees'));
        $row = $rows->firstWhere('user_id', $employee->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['paid_this_period']);
        $this->assertEquals(5200.0, $row['net_amount_this_period']);
        $this->assertEquals(200.0, $row['commission_amount']);
        $this->assertEquals(300.0, $row['unsettled_advances']);
    }

    public function test_payroll_summary_is_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/reports/payroll-summary')->assertStatus(422);
    }

    public function test_payroll_summary_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);

        User::factory()->create(['company_id' => $company->id, 'branch_id' => $branchA->id, 'name' => 'Branch A Employee']);
        User::factory()->create(['company_id' => $company->id, 'branch_id' => $branchB->id, 'name' => 'Branch B Employee']);

        $response = $this->getJson("/api/reports/payroll-summary?branch_id={$branchA->id}")->assertOk();
        $names = collect($response->json('data.employees'))->pluck('name');

        // The manager itself (no branch) plus the requesting company's other
        // users would otherwise appear too -- only Branch A's employee should.
        $this->assertTrue($names->contains('Branch A Employee'));
        $this->assertFalse($names->contains('Branch B Employee'));
    }
}
