<?php

namespace Tests\Feature\Physiotherapy;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\Physiotherapy\PhysioSessionCarePlanService;
use App\Specialties\Physiotherapy\PhysiotherapyModule;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhysioSessionCarePlanTest extends TestCase
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
        $specialty = Specialty::query()->where('key', Specialty::PHYSIOTHERAPY)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(PhysiotherapyModule::class)->seedCatalog($company);

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
        app(PhysiotherapyModule::class)->seedCatalog($company);

        $specialty = Specialty::query()->where('key', Specialty::PHYSIOTHERAPY)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $specialty->id)->get();

        $this->assertCount(4, $items);
        foreach (['phy_initial_evaluation', 'phy_therapy_session', 'phy_reevaluation', 'phy_final_evaluation'] as $code) {
            $this->assertTrue($items->contains('code', $code));
        }
    }

    public function test_confirming_generates_every_milestone_appointment_with_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $plan = app(PhysioSessionCarePlanService::class)->confirmPlan($client, $doctor, 'Lumbar disc herniation', '2026-01-01', '10:00', $doctor->id);

        $this->assertCount(11, $plan->sessions);
        $this->assertStringContainsString('Lumbar disc herniation', $plan->summary);
        $first = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame('2026-01-01', $first->appointment->date->format('Y-m-d'));
        $this->assertSame('Lumbar disc herniation', $first->clinical_data['diagnosis']);
        $last = $plan->sessions->firstWhere('session_index', 10);
        $this->assertSame('2026-01-29', $last->appointment->date->format('Y-m-d'));
        $this->assertGreaterThan(0, $client->treatmentCharges()->sum('amount'));
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/physio-session-care-plan/confirm", [
            'diagnosis' => 'Lumbar disc herniation',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::PHYSIOTHERAPY);
        $response->assertJsonCount(11, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/physio-session-care-plan/confirm", [
            'diagnosis' => 'Lumbar disc herniation',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
