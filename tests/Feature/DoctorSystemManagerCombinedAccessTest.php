<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A doctor who is ALSO a system manager must get every admin capability on
 * top of their own doctor view, not be boxed into "just their own patients"
 * the way a plain doctor is -- see User::isDoctorOnly(), which every
 * hard-scoping/ownership check in DoctorOwnDataScopingTest's plain-doctor
 * world now goes through instead of a bare is_doctor check.
 */
class DoctorSystemManagerCombinedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeDoctor(Company $company, ?string $specialtyKey = Specialty::DENTAL): User
    {
        $specialtyId = $specialtyKey ? Specialty::query()->where('key', $specialtyKey)->value('id') : null;

        return User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $specialtyId,
        ]);
    }

    protected function makeDoctorAdmin(Company $company, ?string $specialtyKey = Specialty::DENTAL): User
    {
        $doctor = $this->makeDoctor($company, $specialtyKey);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $doctor->roles()->sync([$role->id]);

        return $doctor->refresh();
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function enroll(Company $company, Client $client, User $doctor): void
    {
        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $doctor->specialty_id,
            'primary_doctor_id' => $doctor->id,
        ]);
    }

    protected function makeAppointment(Company $company, User $doctor, Client $client): Appointment
    {
        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => '2026-08-20',
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    public function test_a_doctor_admin_can_view_update_and_delete_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($doctorAdmin);

        $this->getJson("/api/clients/{$client->id}")->assertOk();
        $this->putJson("/api/clients/{$client->id}", ['name' => 'Renamed'])->assertOk();
        $this->deleteJson("/api/clients/{$client->id}")->assertOk();
    }

    public function test_a_doctor_admin_sees_every_patient_not_only_their_own(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $ownClient = $this->makeClient($company);
        $otherClient = $this->makeClient($company);
        $this->enroll($company, $ownClient, $doctorAdmin);
        $this->enroll($company, $otherClient, $ownerDoctor);
        Sanctum::actingAs($doctorAdmin);

        $response = $this->getJson('/api/clients')->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_a_doctor_admin_can_filter_the_patient_list_by_another_doctor(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $otherClient = $this->makeClient($company);
        $this->enroll($company, $otherClient, $ownerDoctor);
        $this->enroll($company, $this->makeClient($company), $doctorAdmin);
        Sanctum::actingAs($doctorAdmin);

        $response = $this->getJson("/api/clients?doctor_id={$ownerDoctor->id}")->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame($otherClient->id, $response->json('data.0.id'));
    }

    public function test_a_doctor_admin_can_view_another_doctors_appointment(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $ownerDoctor, $client);
        Sanctum::actingAs($doctorAdmin);

        $this->getJson("/api/appointments/{$appointment->id}")->assertOk();
    }

    public function test_a_doctor_admin_can_filter_appointments_by_another_doctor(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $client = $this->makeClient($company);
        $this->makeAppointment($company, $ownerDoctor, $client);
        Sanctum::actingAs($doctorAdmin);

        $response = $this->getJson("/api/appointments?doctor_id={$ownerDoctor->id}")->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_doctor_admin_can_view_and_manage_another_doctors_schedule(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $ownerDoctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(Weekday::cases())->map(fn ($d) => ['weekday' => $d->value])->all());
        Sanctum::actingAs($doctorAdmin);

        $this->getJson("/api/doctors/{$ownerDoctor->id}/schedule")->assertOk();
        $this->putJson("/api/doctors/{$ownerDoctor->id}/schedule", [
            'start_time' => '10:00',
            'end_time' => '18:00',
            'slot_minutes' => 30,
            'working_days' => ['monday', 'tuesday'],
        ])->assertOk();
    }

    public function test_a_doctor_admin_can_use_the_ai_assistant_on_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $doctorAdmin = $this->makeDoctorAdmin($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($doctorAdmin);

        $this->getJson("/api/clients/{$client->id}/ai-conversation")->assertOk();
    }

    public function test_a_plain_doctor_who_is_not_also_an_admin_is_still_hard_scoped(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}")->assertStatus(422);

        $response = $this->getJson('/api/clients')->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_resolve_treating_doctor_defaults_a_doctor_admin_to_themselves_but_allows_picking_another(): void
    {
        $company = Company::factory()->create();
        $doctorAdmin = $this->makeDoctorAdmin($company, Specialty::INTERNAL_MEDICINE);
        $otherDoctor = $this->makeDoctor($company, Specialty::INTERNAL_MEDICINE);
        foreach ([$doctorAdmin, $otherDoctor] as $eachDoctor) {
            $eachDoctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
                ->workingDays()->createMany(collect(Weekday::cases())->map(fn ($d) => ['weekday' => $d->value])->all());
        }
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctorAdmin);

        $this->postJson("/api/clients/{$client->id}/chronic-care-plan/confirm", [
            'condition' => 'Hypertension',
            'start_date' => now()->addDay()->toDateString(),
            'preferred_start_time' => '10:00',
        ])->assertCreated()->assertJsonPath('data.doctor_id', $doctorAdmin->id);

        $this->postJson("/api/clients/{$client->id}/chronic-care-plan/confirm", [
            'doctor_id' => $otherDoctor->id,
            'condition' => 'Diabetes',
            'start_date' => now()->addDays(2)->toDateString(),
            'preferred_start_time' => '11:00',
        ])->assertCreated()->assertJsonPath('data.doctor_id', $otherDoctor->id);
    }
}
