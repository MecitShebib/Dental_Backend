<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_dashboard_stats_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($user);

        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);

        $clientA = $this->makeClient($company, $branchA);
        $clientB = $this->makeClient($company, $branchB);

        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientA->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientB->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '11:00:00', 'duration_minutes' => 30,
        ]);

        Payment::create(['client_id' => $clientA->id, 'payment_date' => '2026-08-10', 'amount' => 500, 'payment_method' => 'cash']);
        Payment::create(['client_id' => $clientB->id, 'payment_date' => '2026-08-10', 'amount' => 900, 'payment_method' => 'cash']);

        $query = ['date_from' => '2026-08-10', 'date_to' => '2026-08-10', 'branch_id' => $branchA->id];

        $this->getJson('/api/dashboard/stats?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.appointments.total', 1)
            ->assertJsonPath('data.income.total', 500);

        $this->getJson('/api/dashboard/stats?'.http_build_query(['date_from' => '2026-08-10', 'date_to' => '2026-08-10']))
            ->assertOk()
            ->assertJsonPath('data.appointments.total', 2)
            ->assertJsonPath('data.income.total', 1400);
    }

    public function test_dashboard_stats_can_be_filtered_by_specialty(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $gyneDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        Sanctum::actingAs($user);

        $clientA = $this->makeClient($company);
        $clientB = $this->makeClient($company);

        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientA->id, 'doctor_id' => $dentalDoctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientB->id, 'doctor_id' => $gyneDoctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '11:00:00', 'duration_minutes' => 30,
        ]);

        $query = ['date_from' => '2026-08-10', 'date_to' => '2026-08-10', 'specialty' => 'dental'];

        $this->getJson('/api/dashboard/stats?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.appointments.total', 1);

        $this->getJson('/api/dashboard/stats?'.http_build_query(['date_from' => '2026-08-10', 'date_to' => '2026-08-10']))
            ->assertOk()
            ->assertJsonPath('data.appointments.total', 2);
    }

    public function test_appointments_list_can_be_filtered_by_branch(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($user);

        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);

        $clientA = $this->makeClient($company, $branchA);
        $clientB = $this->makeClient($company, $branchB);

        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientA->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $clientB->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-10',
            'start_time' => '11:00:00', 'duration_minutes' => 30,
        ]);

        $this->getJson("/api/appointments?branch_id={$branchA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
