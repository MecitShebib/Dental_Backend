<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
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
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: null,
            branchId: null,
            specialtyKey: $specialtyKey,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }
}
