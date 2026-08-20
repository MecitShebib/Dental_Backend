<?php

namespace Tests\Feature\InternalMedicine;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\InternalMedicine\ChronicCarePlanService;
use App\Specialties\InternalMedicine\InternalMedicineModule;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChronicCarePlanTest extends TestCase
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
        $internalMedicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $internalMedicine->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(InternalMedicineModule::class)->seedCatalog($company);

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

    public function test_the_internal_medicine_catalog_is_seeded_with_four_items(): void
    {
        $company = Company::factory()->create();
        app(InternalMedicineModule::class)->seedCatalog($company);

        $internalMedicine = Specialty::query()->where('key', Specialty::INTERNAL_MEDICINE)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $internalMedicine->id)->get();

        $this->assertCount(4, $items);
        $this->assertTrue($items->contains('code', 'chronic_initial_assessment'));
        $this->assertTrue($items->contains('code', 'chronic_followup_visit'));
        $this->assertTrue($items->contains('code', 'lab_panel'));
        $this->assertTrue($items->contains('code', 'vital_signs_check'));
    }

    public function test_confirming_a_chronic_care_plan_generates_five_milestone_appointments_with_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $plan = app(ChronicCarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            'Type 2 Diabetes',
            '2026-01-01',
            '10:00',
            $doctor->id,
        );

        $this->assertCount(5, $plan->sessions);
        $this->assertStringContainsString('Type 2 Diabetes', $plan->summary);

        $firstSession = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame('2026-01-01', $firstSession->appointment->date->format('Y-m-d'));
        $this->assertSame('Type 2 Diabetes', $firstSession->clinical_data['condition']);

        $totalCharged = $client->treatmentCharges()->sum('amount');
        $this->assertGreaterThan(0, $totalCharged);
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/chronic-care-plan/confirm", [
            'condition' => 'Hypertension',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::INTERNAL_MEDICINE);
        $response->assertJsonCount(5, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/chronic-care-plan/confirm", [
            'condition' => 'Hypertension',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
