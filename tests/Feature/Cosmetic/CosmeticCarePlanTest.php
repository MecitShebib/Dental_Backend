<?php

namespace Tests\Feature\Cosmetic;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\Cosmetic\CosmeticCarePlanService;
use App\Specialties\Cosmetic\CosmeticModule;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CosmeticCarePlanTest extends TestCase
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
        $cosmetic = Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $cosmetic->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(CosmeticModule::class)->seedCatalog($company);

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

    public function test_the_cosmetic_catalog_is_seeded_with_four_items(): void
    {
        $company = Company::factory()->create();
        app(CosmeticModule::class)->seedCatalog($company);

        $cosmetic = Specialty::query()->where('key', Specialty::COSMETIC)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $cosmetic->id)->get();

        $this->assertCount(4, $items);
        $this->assertTrue($items->contains('code', 'cosmetic_consultation'));
        $this->assertTrue($items->contains('code', 'laser_session'));
        $this->assertTrue($items->contains('code', 'botox_session'));
        $this->assertTrue($items->contains('code', 'filler_session'));
    }

    public function test_confirming_a_package_generates_a_consultation_plus_n_sessions(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $plan = app(CosmeticCarePlanService::class)->confirmPlan(
            $client,
            $doctor,
            'laser_session',
            4,
            14,
            '2026-01-01',
            '10:00',
            $doctor->id,
        );

        // 1 consultation + 4 treatment sessions.
        $this->assertCount(5, $plan->sessions);

        $consultation = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame('2026-01-01', $consultation->appointment->date->format('Y-m-d'));

        $lastSession = $plan->sessions->firstWhere('session_index', 4);
        $this->assertSame('2026-02-26', $lastSession->appointment->date->format('Y-m-d')); // start + 4*14=56 days
        $this->assertSame(4, $lastSession->clinical_data['session_number']);

        $totalCharged = $client->treatmentCharges()->sum('amount');
        $this->assertGreaterThan(0, $totalCharged);
    }

    public function test_an_unknown_treatment_code_is_rejected(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/cosmetic-care-plan/confirm", [
            'treatment_code' => 'not_a_real_treatment',
            'session_count' => 3,
            'interval_days' => 14,
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('treatment_code');
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/cosmetic-care-plan/confirm", [
            'treatment_code' => 'botox_session',
            'session_count' => 2,
            'interval_days' => 30,
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::COSMETIC);
        $response->assertJsonCount(3, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/cosmetic-care-plan/confirm", [
            'treatment_code' => 'botox_session',
            'session_count' => 2,
            'interval_days' => 30,
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
