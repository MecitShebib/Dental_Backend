<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CapitalTransaction;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSystemManager(int $companyId): User
    {
        $user = User::factory()->create(['company_id' => $companyId, 'is_doctor' => false]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $user->roles()->sync([$role->id]);

        return $user->refresh();
    }

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-ACT-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Activity Log Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_non_system_manager_cannot_view_the_activity_log(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $this->getJson('/api/activity-log')->assertStatus(422);
    }

    public function test_a_system_manager_can_view_the_activity_log(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $this->getJson('/api/activity-log')->assertOk();
    }

    public function test_creating_a_client_appears_with_its_category_and_actor(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company->id);

        $response = $this->getJson('/api/activity-log')->assertOk();
        $rows = $response->json('data.data');

        $row = collect($rows)->firstWhere('category', 'patient');
        $this->assertNotNull($row);
        $this->assertSame('created', $row['action']);
        $this->assertSame($manager->id, $row['user']['id']);
        $this->assertSame($client->id, $row['client']['id']);
    }

    public function test_creating_an_appointment_is_logged_under_the_appointment_category_with_its_client(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company->id);

        Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ]);

        $response = $this->getJson('/api/activity-log?category=appointment')->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame('appointment', $rows[0]['category']);
        $this->assertSame($client->id, $rows[0]['client']['id']);
    }

    public function test_an_accounting_entry_is_logged_with_a_subject_label_instead_of_a_client(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        Expense::create([
            'company_id' => $company->id,
            'category' => 'rent',
            'vendor_name' => 'Acme Landlord LLC',
            'amount' => 500,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/activity-log?category=accounting')->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['client']);
        $this->assertSame('Acme Landlord LLC', $rows[0]['subject_label']);
    }

    public function test_changing_a_users_password_is_logged_as_its_own_action_without_leaking_the_value(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $staff = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $staff->update(['password' => 'a-new-strong-P@ssw0rd']);

        $response = $this->getJson('/api/activity-log?action=password_changed')->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame('password_changed', $rows[0]['action']);
        $this->assertSame('user', $rows[0]['category']);
        $this->assertSame($staff->name, $rows[0]['subject_label']);
        $this->assertNotContains('password', $rows[0]['changed_fields'] ?? []);
    }

    public function test_logging_in_alone_does_not_create_a_noisy_activity_log_entry(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->forceFill(['last_login_at' => now()])->save();

        $response = $this->getJson('/api/activity-log?category=user')->assertOk();
        $rows = collect($response->json('data.data'))->where('action', '!=', 'created');

        $this->assertCount(0, $rows);
    }

    public function test_filtering_by_a_specific_user(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);

        Sanctum::actingAs($doctorA);
        $clientOne = $this->makeClient($company->id);
        Sanctum::actingAs($doctorB);
        $this->makeClient($company->id);
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/activity-log?user_id={$doctorA->id}")->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame($clientOne->id, $rows[0]['client']['id']);
    }

    public function test_filtering_by_role_narrows_to_doctors_or_staff(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);

        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $this->makeClient($company->id);

        $assistant = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        Sanctum::actingAs($assistant);
        $this->makeClient($company->id);

        Sanctum::actingAs($manager);

        $doctorRows = $this->getJson('/api/activity-log?category=patient&role=doctor')->assertOk()->json('data.data');
        $this->assertCount(1, $doctorRows);
        $this->assertTrue($doctorRows[0]['user']['is_doctor']);

        $staffRows = $this->getJson('/api/activity-log?category=patient&role=staff')->assertOk()->json('data.data');
        $this->assertCount(1, $staffRows);
        $this->assertFalse($staffRows[0]['user']['is_doctor']);
    }

    public function test_filtering_by_client_id_only_returns_that_patients_actions(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $clientOne = $this->makeClient($company->id);
        $this->makeClient($company->id);

        $response = $this->getJson("/api/activity-log?client_id={$clientOne->id}")->assertOk();
        $rows = $response->json('data.data');

        $this->assertCount(1, $rows);
        $this->assertSame($clientOne->id, $rows[0]['client']['id']);
    }

    public function test_filtering_by_date_range_excludes_entries_outside_it(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company->id);
        AuditLog::query()->where('auditable_id', $client->id)->update(['created_at' => now()->subDays(10)]);

        $inRange = $this->getJson('/api/activity-log?date_from='.now()->subDays(1)->toDateString())
            ->assertOk()->json('data.data');
        $this->assertCount(0, collect($inRange)->where('category', 'patient')->all());

        $outOfRange = $this->getJson('/api/activity-log?date_from='.now()->subDays(20)->toDateString().'&date_to='.now()->subDays(5)->toDateString())
            ->assertOk()->json('data.data');
        $this->assertCount(1, collect($outOfRange)->where('category', 'patient')->all());
    }

    public function test_it_never_shows_another_companys_entries(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $manager = $this->makeSystemManager($companyA->id);

        $otherCompanysClient = $this->makeClient($companyB->id);

        Sanctum::actingAs($manager);
        $rows = $this->getJson('/api/activity-log')->assertOk()->json('data.data');

        // The manager's own creation just above is a legitimate company-A
        // "user" entry -- what must never appear is anything referencing the
        // other company's client.
        $this->assertCount(0, collect($rows)->where('client.id', $otherCompanysClient->id)->all());
    }

    public function test_response_is_paginated_with_links_and_meta(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        $this->makeClient($company->id);

        $response = $this->getJson('/api/activity-log')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);
        $this->assertSame(100, $response->json('data.meta.per_page'));
    }

    public function test_a_capital_transaction_falls_under_the_accounting_category(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeSystemManager($company->id);
        Sanctum::actingAs($manager);

        CapitalTransaction::create([
            'company_id' => $company->id,
            'type' => 'injection',
            'amount' => 10000,
            'party_name' => 'Founder',
            'transaction_date' => now()->toDateString(),
        ]);

        $rows = $this->getJson('/api/activity-log?category=accounting')->assertOk()->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame('Founder', $rows[0]['subject_label']);
    }
}
