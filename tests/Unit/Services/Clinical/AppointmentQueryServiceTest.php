<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Clinical\AppointmentQueryService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentQueryServiceTest extends TestCase
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

    private function makeAppointment(Company $company, User $doctor, string $clientName): Appointment
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $clientName,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);

        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    #[DataProvider('specialtyKeys')]
    public function test_specialty_filter_only_returns_appointments_with_a_doctor_of_that_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();
        $matchingDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $otherSpecialty->id]);
        // Non-doctor acting user -- a doctor acting user would force-scope
        // doctor_id to themselves regardless of the specialty filter,
        // which isn't what this test is exercising.
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        $this->makeAppointment($company, $matchingDoctor, 'Matching Patient');
        $this->makeAppointment($company, $otherDoctor, 'Other Patient');

        $result = app(AppointmentQueryService::class)->list($actingUser, ['specialty' => $specialtyKey]);

        $this->assertCount(1, $result->items());
        $this->assertSame('Matching Patient', $result->items()[0]->client->name);
    }

    public function test_doctor_id_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $actingUser = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $this->makeAppointment($company, $doctorA, 'A Patient');
        $this->makeAppointment($company, $doctorB, 'B Patient');

        $result = app(AppointmentQueryService::class)->list($actingUser, ['doctor_id' => $doctorA->id]);

        $this->assertCount(1, $result->items());
        $this->assertSame('A Patient', $result->items()[0]->client->name);
    }

    public function test_a_doctor_only_ever_sees_their_own_appointments_even_if_another_doctor_id_is_requested(): void
    {
        $company = Company::factory()->create();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $this->makeAppointment($company, $doctorA, 'A Patient');
        $this->makeAppointment($company, $doctorB, 'B Patient');

        $result = app(AppointmentQueryService::class)->list($doctorA, ['doctor_id' => $doctorB->id]);

        $this->assertCount(1, $result->items());
        $this->assertSame('A Patient', $result->items()[0]->client->name);
    }
}
