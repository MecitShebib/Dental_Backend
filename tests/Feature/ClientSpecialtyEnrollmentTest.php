<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Specialties\Gynecology\GynecologyModule;
use App\Specialties\Gynecology\PrenatalCarePlanService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSpecialtyEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeDoctor(Company $company, string $specialtyKey): User
    {
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        Subscription::query()->where('company_id', $company->id)->where('specialty_id', $specialty->id)->firstOr(function () use ($company, $specialty) {
            Subscription::create([
                'company_id' => $company->id,
                'specialty_id' => $specialty->id,
                'plan_name' => 'Test Plan',
                'status' => 'active',
                'starts_at' => now()->subDay()->toDateString(),
            ]);
        });

        return $doctor;
    }

    public function test_a_doctor_adding_a_patient_auto_enrolls_them_under_the_doctors_specialty(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::DENTAL);
        Sanctum::actingAs($doctor);

        $response = $this->postJson('/api/clients', [
            'name' => 'New Patient',
            'phone' => '+963900011001',
            'gender' => 'male',
        ])->assertCreated();

        $clientId = $response->json('data.id');
        $record = ClientSpecialtyRecord::query()->where('client_id', $clientId)->firstOrFail();
        $this->assertSame(Specialty::DENTAL, $record->specialty->key);
        $this->assertSame($doctor->id, $record->primary_doctor_id);
    }

    public function test_a_system_manager_adding_a_patient_enrolls_by_explicit_specialty_id_with_no_doctor(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/clients', [
            'name' => 'New Patient',
            'phone' => '+963900011002',
            'gender' => 'male',
            'specialty_id' => $dental->id,
        ])->assertCreated();

        $record = ClientSpecialtyRecord::query()->where('client_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame(Specialty::DENTAL, $record->specialty->key);
        $this->assertNull($record->primary_doctor_id);
    }

    public function test_booking_an_appointment_for_an_unenrolled_client_auto_enrolls_them(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::DENTAL);
        $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->map(fn ($d) => ['weekday' => $d])->all());
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'CL-1', 'name' => 'Walk-in', 'phone' => '+963900011003', 'status' => 'new']);
        Sanctum::actingAs($doctor);

        $this->assertDatabaseMissing('client_specialty_records', ['client_id' => $client->id]);

        $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        $record = ClientSpecialtyRecord::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame($doctor->id, $record->primary_doctor_id);
    }

    public function test_a_second_doctor_does_not_steal_an_already_claimed_patient(): void
    {
        $company = Company::factory()->create();
        $firstDoctor = $this->makeDoctor($company, Specialty::DENTAL);
        $secondDoctor = $this->makeDoctor($company, Specialty::DENTAL);
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'CL-2', 'name' => 'Shared Patient', 'phone' => '+963900011004', 'status' => 'new']);

        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $firstDoctor);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $secondDoctor);

        $record = ClientSpecialtyRecord::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame($firstDoctor->id, $record->primary_doctor_id);
    }

    public function test_a_doctor_only_sees_their_own_patients_on_the_patients_list(): void
    {
        $company = Company::factory()->create();
        $doctorA = $this->makeDoctor($company, Specialty::DENTAL);
        $doctorB = $this->makeDoctor($company, Specialty::DENTAL);
        $clientA = Client::create(['company_id' => $company->id, 'client_code' => 'CL-3', 'name' => 'Patient A', 'phone' => '+963900011005', 'status' => 'new']);
        $clientB = Client::create(['company_id' => $company->id, 'client_code' => 'CL-4', 'name' => 'Patient B', 'phone' => '+963900011006', 'status' => 'new']);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($clientA, $doctorA);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($clientB, $doctorB);

        Sanctum::actingAs($doctorA);
        $response = $this->getJson('/api/clients')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Patient A', $names);
        $this->assertNotContains('Patient B', $names);
    }

    public function test_a_system_manager_sees_all_patients_of_the_selected_specialty(): void
    {
        $company = Company::factory()->create();
        $doctorA = $this->makeDoctor($company, Specialty::DENTAL);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        $dentalClient = Client::create(['company_id' => $company->id, 'client_code' => 'CL-5', 'name' => 'Dental Patient', 'phone' => '+963900011007', 'status' => 'new']);
        $gyneClient = Client::create(['company_id' => $company->id, 'client_code' => 'CL-6', 'name' => 'Gyne Patient', 'phone' => '+963900011008', 'status' => 'new']);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($dentalClient, $doctorA);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($gyneClient, $doctorB);

        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/clients?specialty=dental')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Dental Patient', $names);
        $this->assertNotContains('Gyne Patient', $names);
    }

    public function test_the_appointments_list_can_be_filtered_by_specialty(): void
    {
        $company = Company::factory()->create();
        $dentalDoctor = $this->makeDoctor($company, Specialty::DENTAL);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $gyneDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        $dentalClient = Client::create(['company_id' => $company->id, 'client_code' => 'CL-8', 'name' => 'Dental Patient', 'phone' => '+963900011010', 'status' => 'new']);
        $gyneClient = Client::create(['company_id' => $company->id, 'client_code' => 'CL-9', 'name' => 'Gyne Patient', 'phone' => '+963900011011', 'status' => 'new']);

        Appointment::create([
            'company_id' => $company->id, 'client_id' => $dentalClient->id, 'doctor_id' => $dentalDoctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-20', 'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $gyneClient->id, 'doctor_id' => $gyneDoctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => '2026-08-20', 'start_time' => '11:00:00', 'duration_minutes' => 30,
        ]);

        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/appointments?specialty=dental')->assertOk();

        $clientIds = collect($response->json('data'))->pluck('client_id')->all();
        $this->assertContains($dentalClient->id, $clientIds);
        $this->assertNotContains($gyneClient->id, $clientIds);
    }

    public function test_confirming_a_care_plan_auto_enrolls_the_client(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        app(GynecologyModule::class)->seedCatalog($company);
        $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->map(fn ($d) => ['weekday' => $d])->all());
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'CL-7', 'name' => 'Prenatal Patient', 'phone' => '+963900011009', 'status' => 'new']);
        Sanctum::actingAs($doctor);

        app(PrenatalCarePlanService::class)->confirmPlan($client, $doctor, '2026-01-01', '10:00', $doctor->id);

        $record = ClientSpecialtyRecord::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame(Specialty::GYNECOLOGY, $record->specialty->key);
        $this->assertSame($doctor->id, $record->primary_doctor_id);
    }
}
