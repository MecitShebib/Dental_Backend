<?php

namespace Tests\Feature\Gynecology;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\Gynecology\GynecologyModule;
use App\Specialties\Gynecology\PrenatalCarePlanService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrenatalCarePlanTest extends TestCase
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
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $gynecology->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(GynecologyModule::class)->seedCatalog($company);

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
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_the_gynecology_catalog_is_seeded_with_four_items(): void
    {
        $company = Company::factory()->create();
        app(GynecologyModule::class)->seedCatalog($company);

        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $gynecology->id)->get();

        $this->assertCount(4, $items);
        $this->assertTrue($items->contains('code', 'prenatal_checkup'));
        $this->assertTrue($items->contains('code', 'ultrasound'));
        $this->assertTrue($items->contains('code', 'delivery_package'));
        $this->assertTrue($items->contains('code', 'postpartum_checkup'));
    }

    public function test_confirming_a_prenatal_plan_generates_five_milestone_appointments_with_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        // CarePlan/Appointment auto-fill company_id from the authenticated
        // actor (BelongsToCompany) -- same as calling this through the HTTP
        // endpoint would.
        Sanctum::actingAs($doctor);

        $plan = app(PrenatalCarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            '2026-01-01',
            '10:00',
            $doctor->id,
        );

        $this->assertCount(5, $plan->sessions);
        $this->assertStringContainsString('2026-10-08', $plan->summary); // EDD = LMP + 280 days

        $firstSession = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame(1, $firstSession->clinical_data['trimester']);
        $this->assertSame('2026-02-26', $firstSession->appointment->date->format('Y-m-d')); // LMP + 8 weeks

        $totalCharged = $client->treatmentCharges()->sum('amount');
        $this->assertGreaterThan(0, $totalCharged);
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/prenatal-care-plan/confirm", [
            'last_menstrual_period' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::GYNECOLOGY);
        $response->assertJsonCount(5, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/prenatal-care-plan/confirm", [
            'last_menstrual_period' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
