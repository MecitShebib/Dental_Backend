<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\LabPayment;
use App\Models\PatientLabResult;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A doctor-acting user must never read or modify another doctor's
 * appointments, patients, visits, or lab records -- not just via the list
 * endpoints (already scoped by AppointmentQueryService/ClientQueryService/
 * DashboardStatsService) but also via a single-record id, which nothing
 * previously checked. See AuthorizesOwnDoctorRecords.
 */
class DoctorOwnDataScopingTest extends TestCase
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

    // -- Appointments ------------------------------------------------------

    public function test_a_doctor_cannot_view_update_or_delete_another_doctors_appointment(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $ownerDoctor, $client);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/appointments/{$appointment->id}")->assertStatus(422);
        $this->putJson("/api/appointments/{$appointment->id}", [
            'notes' => 'tampered',
        ])->assertStatus(422);
        $this->deleteJson("/api/appointments/{$appointment->id}")->assertStatus(422);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'notes' => null]);
    }

    public function test_a_doctor_cannot_create_an_appointment_under_another_doctors_id(): void
    {
        $company = Company::factory()->create();
        $actingDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        Sanctum::actingAs($actingDoctor);

        $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $otherDoctor->id,
            'type' => 'booked',
            'date' => '2026-08-21',
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertStatus(422);
    }

    // -- Clients -------------------------------------------------------------

    public function test_a_doctor_cannot_view_update_or_delete_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}")->assertStatus(422);
        $this->putJson("/api/clients/{$client->id}", ['name' => 'Renamed'])->assertStatus(422);
        $this->deleteJson("/api/clients/{$client->id}")->assertStatus(422);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Test Patient']);
    }

    public function test_a_doctor_can_view_update_and_delete_their_own_patient(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $doctor);
        Sanctum::actingAs($doctor);

        $this->getJson("/api/clients/{$client->id}")->assertOk();
        $this->putJson("/api/clients/{$client->id}", ['name' => 'Renamed'])->assertOk();
        $this->deleteJson("/api/clients/{$client->id}")->assertOk();
    }

    // -- Visits ----------------------------------------------------------

    public function test_a_doctor_cannot_list_update_or_delete_another_doctors_visit(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $visit = Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $ownerDoctor->id,
            'visit_date' => '2026-08-18',
            'attendance_status' => 'walk_in',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}/visits")->assertStatus(422);
        $this->putJson("/api/visits/{$visit->id}", ['notes' => 'tampered'])->assertStatus(422);
        $this->deleteJson("/api/visits/{$visit->id}")->assertStatus(422);

        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'notes' => null]);
    }

    public function test_a_doctor_cannot_create_a_visit_under_another_doctors_id(): void
    {
        $company = Company::factory()->create();
        $actingDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        Sanctum::actingAs($actingDoctor);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $otherDoctor->id,
            'visit_date' => '2026-08-18',
        ])->assertStatus(422);
    }

    public function test_a_doctor_cannot_check_in_or_no_show_another_doctors_appointment(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $appointment = $this->makeAppointment($company, $ownerDoctor, $client);
        Sanctum::actingAs($otherDoctor);

        $this->postJson("/api/appointments/{$appointment->id}/check-in", [])->assertStatus(422);
        $this->postJson("/api/appointments/{$appointment->id}/no-show", [])->assertStatus(422);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'scheduled']);
        $this->assertDatabaseCount('visits', 0);
    }

    // -- Lab cases (dental) ------------------------------------------------

    public function test_a_doctor_cannot_update_or_delete_another_doctors_lab_case(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $labCase = LabCase::create([
            'client_id' => $client->id,
            'doctor_id' => $ownerDoctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-01',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->putJson("/api/lab-cases/{$labCase->id}", ['status' => 'ready'])->assertStatus(422);
        $this->deleteJson("/api/lab-cases/{$labCase->id}")->assertStatus(422);

        $this->assertDatabaseHas('lab_cases', ['id' => $labCase->id, 'status' => 'sent']);
    }

    public function test_the_company_wide_lab_case_list_is_hard_scoped_to_a_doctors_own_cases(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        LabCase::create([
            'client_id' => $client->id,
            'doctor_id' => $ownerDoctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-08-01',
        ]);
        Sanctum::actingAs($otherDoctor);

        $response = $this->getJson('/api/lab-cases')->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -- Nested patient reads that had no gate at all (2026-09-22 audit) ----

    public function test_a_doctor_cannot_list_another_doctors_patients_appointments(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $this->makeAppointment($company, $ownerDoctor, $client);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}/appointments")->assertStatus(422);

        Sanctum::actingAs($ownerDoctor);
        $this->getJson("/api/clients/{$client->id}/appointments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_doctor_cannot_read_create_update_or_delete_another_doctors_patients_payments(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $payment = Payment::create([
            'client_id' => $client->id,
            'payment_date' => '2026-09-01',
            'amount' => 500,
            'payment_method' => 'cash',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}/payments")->assertStatus(422);
        $this->postJson("/api/clients/{$client->id}/payments", [
            'payment_date' => '2026-09-02',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(422);
        $this->putJson("/api/payments/{$payment->id}", ['amount' => 1])->assertStatus(422);
        $this->deleteJson("/api/payments/{$payment->id}")->assertStatus(422);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 500, 'deleted_at' => null]);
    }

    public function test_a_doctor_cannot_view_another_doctors_patients_invoice(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $payment = Payment::create([
            'client_id' => $client->id,
            'payment_date' => '2026-09-01',
            'amount' => 750,
            'payment_method' => 'cash',
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'payment_id' => $payment->id,
            'invoice_number' => 1,
            'amount' => 750,
            'issued_date' => '2026-09-01',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/invoices/{$invoice->id}")->assertStatus(422);

        Sanctum::actingAs($ownerDoctor);
        $this->getJson("/api/invoices/{$invoice->id}")->assertOk();
    }

    public function test_a_doctor_cannot_use_the_ai_assistant_on_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}/ai-conversation")->assertStatus(422);
        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['description' => 'Injected fee', 'amount' => 9999]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('treatment_charges', 0);
    }

    public function test_a_doctor_cannot_list_add_or_delete_payments_on_another_doctors_lab_case(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $labCase = LabCase::create([
            'client_id' => $client->id,
            'doctor_id' => $ownerDoctor->id,
            'work_type' => 'crown',
            'sent_date' => '2026-09-01',
            'lab_cost' => 1000,
        ]);
        $labPayment = LabPayment::create([
            'lab_case_id' => $labCase->id,
            'payment_date' => '2026-09-02',
            'amount' => 400,
            'payment_method' => 'cash',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/lab-cases/{$labCase->id}/payments")->assertStatus(422);
        $this->postJson("/api/lab-cases/{$labCase->id}/payments", [
            'payment_date' => '2026-09-03',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(422);
        $this->deleteJson("/api/lab-payments/{$labPayment->id}")->assertStatus(422);

        $this->assertDatabaseCount('lab_payments', 1);
        $this->assertDatabaseHas('lab_payments', ['id' => $labPayment->id, 'deleted_at' => null]);
    }

    // -- Write paths: naming yourself as doctor_id was the only check -------

    public function test_a_doctor_cannot_create_an_appointment_for_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $otherDoctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(Weekday::cases())->map(fn ($d) => ['weekday' => $d->value])->all());
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $otherDoctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'charge_items' => [['description' => 'Injected fee', 'amount' => 5000]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('treatment_charges', 0);
    }

    public function test_a_doctor_cannot_create_a_visit_for_another_doctors_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $otherDoctor->id,
            'visit_date' => now()->toDateString(),
            'notes' => 'injected',
            'charge_items' => [['description' => 'Injected fee', 'amount' => 5000]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('treatment_charges', 0);
    }

    public function test_an_appointment_cannot_be_booked_for_another_companys_client(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(Weekday::cases())->map(fn ($d) => ['weekday' => $d->value])->all());
        $foreignClient = $this->makeClient($otherCompany);
        Sanctum::actingAs($doctor);

        $this->postJson('/api/appointments', [
            'client_id' => $foreignClient->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertStatus(422)->assertJsonValidationErrors('client_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    // -- Enrollment service (defense in depth behind the controllers) ------

    public function test_enrolling_a_doctor_onto_a_colleagues_patient_is_refused(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $otherDoctor = $this->makeDoctor($company);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        Sanctum::actingAs($otherDoctor);

        $this->expectException(ValidationException::class);

        try {
            app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($client, $otherDoctor);
        } finally {
            $this->assertDatabaseHas('client_specialty_records', [
                'client_id' => $client->id,
                'primary_doctor_id' => $ownerDoctor->id,
            ]);
        }
    }

    public function test_enrolling_stays_a_silent_no_op_for_an_unclaimed_or_already_owned_patient(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->makeDoctor($company);
        $unclaimed = $this->makeClient($company);
        $own = $this->makeClient($company);
        $this->enroll($company, $own, $doctor);
        Sanctum::actingAs($doctor);

        $service = app(ClientSpecialtyEnrollmentService::class);
        $service->ensureEnrolled($unclaimed, $doctor);
        $service->ensureEnrolled($own, $doctor);

        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $unclaimed->id,
            'primary_doctor_id' => $doctor->id,
        ]);
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $own->id,
            'primary_doctor_id' => $doctor->id,
        ]);
    }

    public function test_a_non_doctor_can_still_book_any_doctor_for_any_patient(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company);
        $secondDoctor = $this->makeDoctor($company);
        $secondDoctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30])
            ->workingDays()->createMany(collect(Weekday::cases())->map(fn ($d) => ['weekday' => $d->value])->all());
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $secondDoctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        // ...and the second doctor still doesn't steal the patient.
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'primary_doctor_id' => $ownerDoctor->id,
        ]);
    }

    // -- Patient lab results (non-dental specialties) -----------------------

    public function test_a_doctor_cannot_list_update_or_delete_another_doctors_lab_result(): void
    {
        $company = Company::factory()->create();
        $ownerDoctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $otherDoctor = $this->makeDoctor($company, Specialty::GYNECOLOGY);
        $client = $this->makeClient($company);
        $this->enroll($company, $client, $ownerDoctor);
        $labResult = PatientLabResult::create([
            'client_id' => $client->id,
            'doctor_id' => $ownerDoctor->id,
            'specialty_id' => $ownerDoctor->specialty_id,
            'test_name' => 'Hemoglobin A1c',
            'test_date' => '2026-08-18',
        ]);
        Sanctum::actingAs($otherDoctor);

        $this->getJson("/api/clients/{$client->id}/lab-results")->assertStatus(422);
        $this->putJson("/api/lab-results/{$labResult->id}", ['result_value' => 'tampered'])->assertStatus(422);
        $this->deleteJson("/api/lab-results/{$labResult->id}")->assertStatus(422);

        $this->assertDatabaseHas('patient_lab_results', ['id' => $labResult->id, 'result_value' => null]);
    }
}
