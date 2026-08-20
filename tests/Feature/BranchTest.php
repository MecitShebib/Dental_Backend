<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SalaryPayment;
use App\Models\Specialty;
use App\Models\TreatmentCharge;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function makeClient(Company $company, ?Branch $branch = null): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_branch_can_be_created_up_to_the_subscription_limit(): void
    {
        $company = Company::factory()->create();
        $company->currentSubscription->update(['max_branches' => 2]);
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->postJson('/api/branches', ['name' => 'Main Branch'])->assertCreated();
        $this->postJson('/api/branches', ['name' => 'Second Branch'])->assertCreated();

        $response = $this->postJson('/api/branches', ['name' => 'Third Branch']);
        $response->assertStatus(422);

        $this->assertSame(2, Branch::query()->count());
    }

    public function test_branches_are_scoped_to_the_companys_own_data(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        Branch::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Branch', 'status' => 'active']);

        $manager = $this->makeManager($ownCompany);
        Sanctum::actingAs($manager);

        $this->getJson('/api/branches')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_branch_with_staff_or_patients_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $branchId = $this->postJson('/api/branches', ['name' => 'Main Branch'])->json('data.id');
        $branch = Branch::find($branchId);
        $this->makeClient($company, $branch);

        $this->deleteJson("/api/branches/{$branchId}")->assertStatus(422);
        $this->assertNotNull(Branch::find($branchId));
    }

    public function test_branch_summary_aggregates_appointments_revenue_debts_and_payroll(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Downtown', 'status' => 'active']);
        $doctor = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'is_doctor' => true]);
        $client = $this->makeClient($company, $branch);

        Appointment::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => now()->toDateString(),
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);

        TreatmentCharge::create(['client_id' => $client->id, 'source_type' => 'manual', 'amount' => 1000, 'description' => 'Crown']);
        Payment::create(['client_id' => $client->id, 'payment_date' => now()->toDateString(), 'amount' => 300, 'payment_method' => 'cash']);

        SalaryPayment::create([
            'company_id' => $company->id, 'user_id' => $doctor->id,
            'period_year' => now()->year, 'period_month' => now()->month,
            'base_salary' => 4000, 'net_amount' => 4000, 'paid_at' => now()->toDateString(),
        ]);

        $response = $this->getJson("/api/branches/{$branch->id}/summary")->assertOk();

        $response->assertJsonPath('data.appointments_today', 1)
            ->assertJsonPath('data.revenue_today', 300)
            ->assertJsonPath('data.patient_debts_total', 700)
            ->assertJsonPath('data.payroll_this_month', 4000)
            ->assertJsonPath('data.staff_count', 1)
            ->assertJsonPath('data.clients_count', 1);
    }

    public function test_subscription_creation_requires_max_branches(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => null, 'is_project_admin' => true, 'status' => 'active']);
        $this->actingAs($admin);

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();

        $this->post(route('admin.subscriptions.store'), [
            'company_id' => $company->id, 'specialty_id' => $dental->id, 'plan_name' => 'Growth', 'status' => 'active',
            'starts_at' => now()->toDateString(), 'max_users' => 5,
        ])->assertSessionHasErrors('max_branches');
    }

    public function test_subscription_update_rejects_reducing_max_branches_below_current_branch_count(): void
    {
        $company = Company::factory()->create();
        $company->currentSubscription->update(['max_branches' => 3]);
        Branch::create(['company_id' => $company->id, 'name' => 'A', 'status' => 'active']);
        Branch::create(['company_id' => $company->id, 'name' => 'B', 'status' => 'active']);

        $admin = User::factory()->create(['company_id' => null, 'is_project_admin' => true, 'status' => 'active']);
        $this->actingAs($admin);

        $subscription = $company->currentSubscription;

        $this->put(route('admin.subscriptions.update', $subscription), [
            'company_id' => $company->id, 'specialty_ids' => [$subscription->specialty_id], 'plan_name' => $subscription->plan_name, 'status' => 'active',
            'starts_at' => $subscription->starts_at->toDateString(), 'max_users' => $subscription->max_users,
            'max_branches' => 1,
        ])->assertSessionHasErrors('max_branches');
    }
}
