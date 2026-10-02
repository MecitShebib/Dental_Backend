<?php

namespace Tests\Feature\GeneralPractice;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\GeneralPractice\GeneralFollowupCarePlanService;
use App\Specialties\GeneralPractice\GeneralPracticeModule;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeneralFollowupCarePlanTest extends TestCase
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
        $specialty = Specialty::query()->where('key', Specialty::GENERAL_PRACTICE)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(GeneralPracticeModule::class)->seedCatalog($company);

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

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'status' => 'new',
        ]);
    }

    public function test_the_catalog_is_seeded_with_four_items(): void
    {
        $company = Company::factory()->create();
        app(GeneralPracticeModule::class)->seedCatalog($company);

        $specialty = Specialty::query()->where('key', Specialty::GENERAL_PRACTICE)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $specialty->id)->get();

        $this->assertCount(4, $items);
        foreach (['gp_examination', 'gp_followup', 'gp_lab_panel', 'gp_checkup'] as $code) {
            $this->assertTrue($items->contains('code', $code));
        }
    }

    public function test_confirming_generates_every_milestone_appointment_with_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $plan = app(GeneralFollowupCarePlanService::class)->confirmPlan($client, $doctor, 'Persistent cough', '2026-01-01', '10:00', $doctor->id);

        $this->assertCount(3, $plan->sessions);
        $this->assertStringContainsString('Persistent cough', $plan->summary);
        $first = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame('2026-01-01', $first->appointment->date->format('Y-m-d'));
        $this->assertSame('Persistent cough', $first->clinical_data['complaint']);
        $last = $plan->sessions->firstWhere('session_index', 2);
        $this->assertSame('2026-04-01', $last->appointment->date->format('Y-m-d'));
        $this->assertGreaterThan(0, $client->treatmentCharges()->sum('amount'));
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/general-followup-care-plan/confirm", [
            'complaint' => 'Persistent cough',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::GENERAL_PRACTICE);
        $response->assertJsonCount(3, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/general-followup-care-plan/confirm", [
            'complaint' => 'Persistent cough',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
