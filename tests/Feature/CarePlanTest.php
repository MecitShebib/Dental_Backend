<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Services\CarePlanService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CarePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $company = $doctor->company;
        $company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::GYNECOLOGY)->value('id'),
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function makeClient(?int $companyId = null): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function nextMonday(): string
    {
        return now()->next('monday')->toDateString();
    }

    public function test_confirming_a_plan_creates_appointments_a_care_plan_and_itemized_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $staff = User::factory()->create(['company_id' => $doctor->company_id]);
        $date = $this->nextMonday();

        // CarePlan/Appointment both auto-fill company_id from the
        // authenticated actor (BelongsToCompany), same as calling this
        // through the (not-yet-built) HTTP endpoint would.
        Sanctum::actingAs($staff);

        $plan = app(CarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            $gynecology,
            'Prenatal care plan',
            [
                [
                    'date' => $date,
                    'start_time' => '10:00',
                    'duration_minutes' => 30,
                    'title' => 'First trimester check-up',
                    'notes' => 'Confirm LMP and estimate due date.',
                    'clinical_data' => ['trimester' => 1, 'lmp' => '2026-06-01'],
                    'charge_items' => [['description' => 'Prenatal visit', 'amount' => 500]],
                ],
                [
                    'date' => $date,
                    'start_time' => '11:00',
                    'duration_minutes' => 45,
                    'title' => 'Ultrasound',
                    'clinical_data' => ['trimester' => 1],
                    'charge_items' => [['description' => 'Ultrasound', 'amount' => 800]],
                ],
            ],
            $staff->id,
            'Initial prenatal plan for a first pregnancy.',
        );

        $this->assertSame($gynecology->id, $plan->specialty_id);
        $this->assertSame($client->id, $plan->client_id);
        $this->assertSame($doctor->id, $plan->doctor_id);
        $this->assertSame($staff->id, $plan->created_by);
        $this->assertCount(2, $plan->sessions);

        $firstSession = $plan->sessions->firstWhere('session_index', 0);
        $this->assertNotNull($firstSession->appointment_id);
        $this->assertSame('10:00', substr($firstSession->appointment->start_time, 0, 5));
        $this->assertSame(1, $firstSession->clinical_data['trimester']);

        $this->assertSame(
            1300.0,
            (float) TreatmentCharge::query()->where('client_id', $client->id)->sum('amount')
        );
    }

    public function test_overlapping_sessions_in_the_same_plan_are_rejected(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $date = $this->nextMonday();

        $this->expectException(ValidationException::class);

        app(CarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            $gynecology,
            'Plan with overlap',
            [
                ['date' => $date, 'start_time' => '10:00', 'duration_minutes' => 60, 'title' => 'A'],
                ['date' => $date, 'start_time' => '10:30', 'duration_minutes' => 30, 'title' => 'B'],
            ],
            $doctor->id,
        );
    }

    public function test_a_session_conflicting_with_an_existing_appointment_is_rejected(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $date = $this->nextMonday();

        Appointment::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => $date,
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
            'end_time' => '10:30:00',
        ]);

        $this->expectException(ValidationException::class);

        app(CarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            $gynecology,
            'Conflicting plan',
            [['date' => $date, 'start_time' => '10:15', 'duration_minutes' => 30, 'title' => 'Overlaps existing']],
            $doctor->id,
        );
    }

    public function test_checking_in_a_care_plan_appointment_retargets_its_charges_to_the_resulting_visit(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $date = $this->nextMonday();

        Sanctum::actingAs($doctor);

        $plan = app(CarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            $gynecology,
            'Plan to check in',
            [['date' => $date, 'start_time' => '10:00', 'duration_minutes' => 30, 'title' => 'Visit', 'charge_items' => [['description' => 'Visit fee', 'amount' => 400]]]],
            $doctor->id,
        );

        $appointment = $plan->sessions->first()->appointment;

        $response = $this->postJson("/api/appointments/{$appointment->id}/check-in");
        $response->assertOk();

        $visitId = $response->json('data.id');

        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => TreatmentCharge::SOURCE_VISIT,
            'source_id' => $visitId,
            'amount' => 400,
        ]);
        $this->assertDatabaseMissing('treatment_charges', [
            'source_type' => TreatmentCharge::SOURCE_AI_PLAN,
            'source_id' => $appointment->id,
        ]);
    }
}
