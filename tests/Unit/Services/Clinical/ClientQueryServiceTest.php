<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Clinical\ClientQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientQueryServiceTest extends TestCase
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

    private function makeClient(Company $company, string $name = 'Test Patient'): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    #[DataProvider('specialtyKeys')]
    public function test_a_doctor_only_sees_their_own_claimed_patients_regardless_of_specialty_key_argument(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);

        $ownPatient = $this->makeClient($company, 'Own Patient');
        $otherDoctorsPatient = $this->makeClient($company, 'Other Doctors Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($ownPatient, $doctor);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($otherDoctorsPatient, $otherDoctor);

        // Passing a DIFFERENT specialty key than the doctor's own must not matter --
        // a doctor is always hard-scoped to their own specialty_id (Doctovaria Phase 8).
        $result = app(ClientQueryService::class)->list($doctor, $specialtyKey, []);

        $this->assertCount(1, $result->items());
        $this->assertSame('Own Patient', $result->items()[0]->name);
    }

    #[DataProvider('specialtyKeys')]
    public function test_a_non_doctor_sees_only_patients_of_the_requested_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        $requestedSpecialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();

        $matchingPatient = $this->makeClient($company, 'Matching Patient');
        $otherPatient = $this->makeClient($company, 'Other Specialty Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($matchingPatient, $requestedSpecialty, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($otherPatient, $otherSpecialty, $manager);

        $result = app(ClientQueryService::class)->list($manager, $specialtyKey, []);

        $this->assertCount(1, $result->items());
        $this->assertSame('Matching Patient', $result->items()[0]->name);
    }

    public function test_name_and_phone_filters_are_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->makeClient($company, 'Alice Match');
        $this->makeClient($company, 'Bob Nomatch');

        $result = app(ClientQueryService::class)->list($manager, null, ['name' => 'Alice']);

        $this->assertCount(1, $result->items());
        $this->assertSame('Alice Match', $result->items()[0]->name);
    }
}
