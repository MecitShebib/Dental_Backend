<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabCaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Only used by the lab-cost-accounting-sync tests: those also read
     * /api/fund/summary and /api/expenses, which require accounting access,
     * unlike plain lab case CRUD.
     */
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

    protected function makeAppointment(Company $company, User $doctor, ?Client $client = null): Appointment
    {
        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client?->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-08-20',
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    public function test_a_lab_case_can_be_created_with_teeth_and_listed_for_the_client(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'teeth' => ['16', '17'],
            'material' => 'zirconia',
            'shade' => 'A2',
            'sent_date' => '2026-08-03',
            'expected_return_date' => '2026-08-10',
            'lab_cost' => 300,
        ])->assertCreated()
            ->assertJsonPath('data.work_type', 'crown')
            ->assertJsonPath('data.teeth', ['16', '17'])
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.doctor_name', $doctor->name);

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_lab_case_cannot_be_created_with_a_non_doctor_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $notADoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $notADoctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
        ])->assertStatus(422)->assertJsonValidationErrors('doctor_id');
    }

    public function test_a_lab_case_can_be_linked_to_the_clients_own_appointment(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $doctor, $client);
        Sanctum::actingAs($user);

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'appointment_id' => $appointment->id,
            'work_type' => 'bridge',
            'sent_date' => '2026-08-03',
        ])->assertCreated()
            ->assertJsonPath('data.appointment_id', $appointment->id)
            ->assertJsonPath('data.appointment_date', '2026-08-20');
    }

    public function test_a_lab_case_cannot_be_linked_to_another_clients_appointment(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        $otherClient = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $doctor, $otherClient);
        Sanctum::actingAs($user);

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'appointment_id' => $appointment->id,
            'work_type' => 'bridge',
            'sent_date' => '2026-08-03',
        ])->assertStatus(422)->assertJsonValidationErrors('appointment_id');
    }

    public function test_a_lab_case_status_and_received_date_can_be_updated(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'denture_full',
            'sent_date' => '2026-08-03',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/lab-cases/{$labCaseId}", [
            'status' => 'ready',
            'received_date' => '2026-08-09',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.received_date', '2026-08-09');
    }

    public function test_a_lab_case_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'veneer',
            'sent_date' => '2026-08-03',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/lab-cases/{$labCaseId}")->assertOk();
        $this->getJson("/api/clients/{$client->id}/lab-cases")->assertJsonCount(0, 'data');
    }

    public function test_the_company_wide_lab_case_list_spans_every_client_and_filters_by_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $clientA = $this->makeClient($company);
        $clientB = $this->makeClient($company);
        Sanctum::actingAs($user);

        $this->postJson("/api/clients/{$clientA->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-01',
        ])->assertCreated();

        $readyCaseId = $this->postJson("/api/clients/{$clientB->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'bridge',
            'sent_date' => '2026-08-02',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/lab-cases/{$readyCaseId}", ['status' => 'ready'])->assertOk();

        $this->getJson('/api/lab-cases')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/lab-cases?status=ready')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', $clientB->name);
    }

    public function test_lab_cases_are_scoped_to_the_companys_own_clients(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherDoctor = User::factory()->create(['company_id' => $otherCompany->id, 'is_doctor' => true]);
        $otherClient = $this->makeClient($otherCompany);

        Sanctum::actingAs($otherUser);
        $this->postJson("/api/clients/{$otherClient->id}/lab-cases", [
            'doctor_id' => $otherDoctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-01',
        ])->assertCreated();

        $ownUser = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($ownUser);

        $this->getJson('/api/lab-cases')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/clients/{$otherClient->id}/lab-cases")->assertNotFound();
    }

    public function test_a_lab_cost_automatically_posts_an_expense_to_the_company_fund(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => 300,
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/fund/summary')
            ->assertJsonPath('data.balance', -300)
            ->assertJsonPath('data.by_source.expense', -300);

        $expenseId = $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->json('data.0.expense_id');
        $this->assertNotNull($expenseId);

        $this->getJson('/api/expenses')
            ->assertJsonPath('data.0.category', 'lab_fees')
            ->assertJsonPath('data.0.amount', 300);
    }

    public function test_updating_the_lab_cost_adjusts_the_linked_expense_and_fund(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => 300,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/lab-cases/{$labCaseId}", ['lab_cost' => 450])->assertOk();

        // Still exactly one expense row -- the same one, amount updated in place.
        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -450);
        $this->getJson('/api/expenses')->assertJsonCount(1, 'data');
    }

    public function test_clearing_the_lab_cost_removes_the_linked_expense_and_fund_impact(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => 300,
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/lab-cases/{$labCaseId}", ['lab_cost' => null])->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
        $this->getJson('/api/expenses')->assertJsonCount(0, 'data');
        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertJsonPath('data.0.expense_id', null);
    }

    public function test_deleting_a_lab_case_removes_its_linked_expense_and_fund_impact(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeManager($company);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($user);

        $labCaseId = $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-03',
            'lab_cost' => 300,
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/lab-cases/{$labCaseId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
        $this->getJson('/api/expenses')->assertJsonCount(0, 'data');
    }
}
