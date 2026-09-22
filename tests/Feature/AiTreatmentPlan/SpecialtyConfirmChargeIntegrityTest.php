<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCharge;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers two real bugs found together in the same report: confirming a
 * non-dental specialty's AI treatment plan left the resulting appointment
 * with no reconstructable "procedures performed" indicator (planned_summary
 * stayed null), and editing/checking in that same appointment's charges
 * afterward silently doubled the client's total services -- the AI-plan's
 * own charge (source_type=ai_plan) was never retargeted onto the
 * appointment before the edited set (source_type=appointment) was synced
 * alongside it, so both got summed. Exercised via gynecology, but the fixed
 * code (SpecialtyAiTreatmentPlanService, AppointmentController::update())
 * is shared verbatim by all 4 non-dental specialties.
 */
class SpecialtyConfirmChargeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $doctor = User::factory()->create(['is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $doctor->company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => null,
            'ai_tokens_used' => 0,
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

    protected function makeClient(Company $company): Client
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Sara',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);
        $this->signKvkkConsent($client);

        return $client;
    }

    public function test_confirming_a_plan_stamps_the_procedures_onto_planned_summary(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $this->postJson("/api/gynecology/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'duration_minutes' => 30,
                'session_description' => 'Prenatal checkup.',
                'procedures' => [
                    ['procedure_code' => 'prenatal_checkup', 'notes' => 'First trimester'],
                ],
                'charge_items' => [
                    ['description' => 'Prenatal Checkup', 'amount' => 800],
                ],
            ]],
        ])->assertCreated();

        $appointment = $client->fresh()->appointments()->firstOrFail();
        $this->assertNotNull($appointment->planned_summary);
        $this->assertStringContainsString('__visit_specialty_procedures__', $appointment->planned_summary);
        $this->assertStringContainsString('prenatal_checkup', $appointment->planned_summary);
    }

    public function test_editing_an_ai_plan_appointments_charges_does_not_double_the_total(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $confirmResponse = $this->postJson("/api/gynecology/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'duration_minutes' => 30,
                'session_description' => 'Prenatal checkup.',
                'procedures' => [
                    ['procedure_code' => 'prenatal_checkup', 'notes' => null],
                ],
                'charge_items' => [
                    ['description' => 'Prenatal Checkup', 'amount' => 800],
                ],
            ]],
        ])->assertCreated();

        $appointmentId = $confirmResponse->json('data.0.id');
        $appointmentDate = $confirmResponse->json('data.0.date');
        $appointmentStartTime = $confirmResponse->json('data.0.start_time');

        $this->assertSame(800.0, (float) TreatmentCharge::query()->where('client_id', $client->id)->sum('amount'));

        // Simulate the doctor re-entering the same service while checking
        // in / editing the appointment, exactly what the check-in modal's
        // "fill them in again" flow produces -- mirrors AppStateApiContext.jsx's
        // updateAppointment(), which always resends the full appointment
        // shape (date/start_time/duration_minutes/doctor_id included), not
        // charge_items alone.
        $this->putJson("/api/gynecology/appointments/{$appointmentId}", [
            'doctor_id' => $doctor->id,
            'date' => $appointmentDate,
            'start_time' => $appointmentStartTime,
            'duration_minutes' => 30,
            'charge_items' => [
                ['description' => 'Prenatal Checkup', 'amount' => 800],
            ],
        ])->assertOk();

        $this->assertSame(
            800.0,
            (float) TreatmentCharge::query()->where('client_id', $client->id)->sum('amount'),
            'Editing the appointment charges must replace the AI plan charge, not add a second one alongside it.',
        );
        $this->assertDatabaseCount('treatment_charges', 1);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => TreatmentCharge::SOURCE_APPOINTMENT,
            'source_id' => $appointmentId,
            'amount' => 800,
        ]);
    }

    public function test_confirming_a_plan_with_a_session_today_marks_it_attended_immediately(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $response = $this->postJson("/api/gynecology/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => now()->toDateString(),
                'start_time' => '11:00',
                'duration_minutes' => 30,
                'session_description' => 'Prenatal checkup.',
                'procedures' => [
                    ['procedure_code' => 'prenatal_checkup', 'notes' => null],
                ],
                'charge_items' => [
                    ['description' => 'Prenatal Checkup', 'amount' => 800],
                ],
            ]],
        ])->assertCreated();

        $appointmentId = $response->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointmentId,
            'attendance_status' => 'attended',
        ]);
        // The charge follows the appointment onto the visit instead of
        // staying orphaned on the now-completed appointment.
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => TreatmentCharge::SOURCE_VISIT,
            'amount' => 800,
        ]);
        $this->assertDatabaseMissing('treatment_charges', [
            'source_type' => TreatmentCharge::SOURCE_AI_PLAN,
            'source_id' => $appointmentId,
        ]);
    }

    public function test_confirming_a_plan_with_a_future_session_leaves_it_scheduled(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $response = $this->postJson("/api/gynecology/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [[
                'date' => now()->addWeek()->toDateString(),
                'start_time' => '11:00',
                'duration_minutes' => 30,
                'session_description' => 'Follow-up.',
                'charge_items' => [
                    ['description' => 'Follow-up', 'amount' => 400],
                ],
            ]],
        ])->assertCreated();

        $appointmentId = $response->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseMissing('visits', ['appointment_id' => $appointmentId]);
    }
}
