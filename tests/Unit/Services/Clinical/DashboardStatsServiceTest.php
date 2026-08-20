<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Visit;
use App\Services\Clinical\DashboardStatsService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public static function specialtyKeys(): array
    {
        return [
            'dental' => [Specialty::DENTAL],
            'gynecology' => [Specialty::GYNECOLOGY],
            'internal_medicine' => [Specialty::INTERNAL_MEDICINE],
            'orthopedics' => [Specialty::ORTHOPEDICS],
            'cosmetic' => [Specialty::COSMETIC],
        ];
    }

    #[DataProvider('specialtyKeys')]
    public function test_appointment_totals_are_scoped_to_the_requested_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();
        $matchingDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $otherSpecialty->id]);
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        foreach ([$matchingDoctor, $otherDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            actingUser: $actingUser,
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: null,
            branchId: null,
            specialtyKey: $specialtyKey,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }

    public function test_income_totals_are_scoped_to_the_requested_specialty(): void
    {
        $company = Company::factory()->create();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $gynDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        foreach ([$dentalDoctor, $gynDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            $visit = Visit::create([
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'visit_date' => now()->toDateString(),
                'attendance_status' => 'attended',
            ]);
            Payment::create([
                'client_id' => $client->id,
                'visit_id' => $visit->id,
                'payment_date' => now()->toDateString(),
                'amount' => 100,
                'payment_method' => 'cash',
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            actingUser: $actingUser,
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: null,
            branchId: null,
            specialtyKey: 'dental',
        );

        $this->assertSame(100.0, $stats['income']['total']);
    }

    public function test_doctor_id_filter_scopes_appointment_totals(): void
    {
        $company = Company::factory()->create();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        foreach ([$doctorA, $doctorB] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            actingUser: $actingUser,
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: $doctorA->id,
            branchId: null,
            specialtyKey: null,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }

    public function test_a_doctor_only_ever_sees_their_own_totals_even_if_another_doctor_id_is_requested(): void
    {
        $company = Company::factory()->create();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);

        foreach ([$doctorA, $doctorB] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            actingUser: $doctorA,
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: $doctorB->id,
            branchId: null,
            specialtyKey: null,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }

    public function test_branch_id_filter_scopes_appointment_totals(): void
    {
        $company = Company::factory()->create();
        $branchA = Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        foreach ([$branchA, $branchB] as $branch) {
            $client = Client::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$branch->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            actingUser: $actingUser,
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: null,
            branchId: $branchA->id,
            specialtyKey: null,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }
}
