<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\Clinical\ClientQueryService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
            'nutrition' => [Specialty::NUTRITION],
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
        // Doctor is ALWAYS gynecology, regardless of the data provider's $specialtyKey.
        $doctorSpecialty = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $doctorSpecialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $doctorSpecialty->id]);

        $ownPatient = $this->makeClient($company, 'Own Patient');
        $otherDoctorsPatient = $this->makeClient($company, 'Other Doctors Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($ownPatient, $doctor);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($otherDoctorsPatient, $otherDoctor);

        // Passing a specialty key (from data provider) that is DIFFERENT from the doctor's own
        // (gynecology) must not matter -- a doctor is always hard-scoped to their own specialty_id
        // (Doctovaria Phase 8). For at least some data-provider cases, $specialtyKey genuinely
        // differs from the doctor's actual specialty.
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
        $alice = $this->makeClient($company, 'Alice Match');
        $bob = $this->makeClient($company, 'Bob Nomatch');

        // Test name filter
        $result = app(ClientQueryService::class)->list($manager, null, ['name' => 'Alice']);
        $this->assertCount(1, $result->items());
        $this->assertSame('Alice Match', $result->items()[0]->name);

        // Test phone filter
        $result = app(ClientQueryService::class)->list($manager, null, ['phone' => $bob->phone]);
        $this->assertCount(1, $result->items());
        $this->assertSame('Bob Nomatch', $result->items()[0]->name);
    }

    public function test_a_manager_never_sees_another_companys_clients_even_with_matching_specialty(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $companyA->id]);
        Sanctum::actingAs($manager);

        $ownClient = $this->makeClient($companyA, 'Own Company Patient');
        $otherCompanyClient = $this->makeClient($companyB, 'Other Company Patient');

        $result = app(ClientQueryService::class)->list($manager, null, []);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Own Company Patient'));
        $this->assertFalse($names->contains('Other Company Patient'));
    }

    public function test_gender_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $male = $this->makeClient($company, 'Male Patient');
        $female = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Female Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);

        $result = app(ClientQueryService::class)->list($manager, null, ['gender' => 'female']);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Female Patient'));
        $this->assertFalse($names->contains('Male Patient'));
    }

    public function test_age_range_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $young = $this->makeClient($company, 'Young Patient');
        $young->update(['age' => 10]);
        $old = $this->makeClient($company, 'Old Patient');
        $old->update(['age' => 70]);

        $result = app(ClientQueryService::class)->list($manager, null, ['age_min' => 18, 'age_max' => 80]);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Old Patient'));
        $this->assertFalse($names->contains('Young Patient'));
    }

    public function test_appointment_date_range_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $withAppointment = $this->makeClient($company, 'Has Appointment');
        $withoutAppointment = $this->makeClient($company, 'No Appointment');

        \App\Models\Appointment::create([
            'company_id' => $company->id,
            'client_id' => $withAppointment->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-10-15',
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);

        $result = app(ClientQueryService::class)->list($manager, null, [
            'appointment_from' => '2026-10-01',
            'appointment_to' => '2026-10-31',
        ]);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Has Appointment'));
        $this->assertFalse($names->contains('No Appointment'));
    }

    public function test_gynecology_blood_type_profile_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        $matching = $this->makeClient($company, 'Blood Type A');
        $other = $this->makeClient($company, 'Blood Type B');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($matching, $gynecology, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($other, $gynecology, $manager);
        \App\Models\GynecologyClientProfile::create(['client_id' => $matching->id, 'blood_type' => 'A']);
        \App\Models\GynecologyClientProfile::create(['client_id' => $other->id, 'blood_type' => 'B']);

        $result = app(ClientQueryService::class)->list($manager, Specialty::GYNECOLOGY, ['blood_type' => 'A']);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Blood Type A'));
        $this->assertFalse($names->contains('Blood Type B'));
    }

    public function test_nutrition_chronic_condition_profile_filter_matches_json_array_membership(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();

        $matching = $this->makeClient($company, 'Diabetic Patient');
        $other = $this->makeClient($company, 'Non-diabetic Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($matching, $nutrition, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($other, $nutrition, $manager);
        \App\Models\NutritionClientProfile::create([
            'client_id' => $matching->id,
            'chronic_conditions' => ['Type 2 diabetes', 'Hypertension'],
        ]);
        \App\Models\NutritionClientProfile::create([
            'client_id' => $other->id,
            'chronic_conditions' => ['Hypertension'],
        ]);

        $result = app(ClientQueryService::class)->list($manager, Specialty::NUTRITION, ['chronic_condition' => 'Type 2 diabetes']);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Diabetic Patient'));
        $this->assertFalse($names->contains('Non-diabetic Patient'));
    }

    public function test_cosmetic_boolean_profile_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);
        $cosmetic = Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();

        $prone = $this->makeClient($company, 'Keloid Prone');
        $notProne = $this->makeClient($company, 'Not Keloid Prone');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($prone, $cosmetic, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($notProne, $cosmetic, $manager);
        \App\Models\CosmeticClientProfile::create(['client_id' => $prone->id, 'keloid_tendency' => true]);
        \App\Models\CosmeticClientProfile::create(['client_id' => $notProne->id, 'keloid_tendency' => false]);

        $result = app(ClientQueryService::class)->list($manager, Specialty::COSMETIC, ['keloid_tendency' => true]);

        $names = collect($result->items())->pluck('name');
        $this->assertTrue($names->contains('Keloid Prone'));
        $this->assertFalse($names->contains('Not Keloid Prone'));
    }

    public function test_a_profile_filter_key_unused_by_the_current_specialty_is_a_no_op(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        $orthoClient = $this->makeClient($company, 'Orthopedics Patient');
        $orthopedics = Specialty::query()->where('key', Specialty::ORTHOPEDICS)->firstOrFail();
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($orthoClient, $orthopedics, $manager);

        // blood_type is a real column on GynecologyClientProfile, but requesting it under
        // orthopedics must not silently exclude everyone or error out -- orthopedics' own
        // applyProfileFilters() branch has no blood_type case, so it must be a no-op.
        $result = app(ClientQueryService::class)->list($manager, Specialty::ORTHOPEDICS, ['blood_type' => 'A']);

        $this->assertCount(1, $result->items());
        $this->assertSame('Orthopedics Patient', $result->items()[0]->name);
    }
}
