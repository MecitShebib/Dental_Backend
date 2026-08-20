<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\TreatmentCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_the_seeder_creates_exactly_five_active_specialties(): void
    {
        $this->assertSame(5, Specialty::query()->count());

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $this->assertTrue($dental->is_active);
        $this->assertSame('Dentavaria', $dental->brand_name);

        // All five are company-subscribable now that every specialty has a
        // real (if v1) backend+frontend -- see each *Module::isBuilt().
        $others = Specialty::query()->where('key', '!=', Specialty::DENTAL)->get();
        $this->assertCount(4, $others);
        $this->assertTrue($others->every(fn (Specialty $specialty) => $specialty->is_active));
    }

    public function test_the_seeder_is_safe_to_run_twice(): void
    {
        $this->seed(SpecialtySeeder::class);

        $this->assertSame(5, Specialty::query()->count());
    }

    public function test_a_company_can_hold_active_subscriptions_for_more_than_one_specialty(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $dental->id,
            'plan_name' => 'Dental Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $gynecology->id,
            'plan_name' => 'Gynecology Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        $activeKeys = $company->activeSpecialties()->pluck('key')->all();

        $this->assertEqualsCanonicalizing([Specialty::DENTAL, Specialty::GYNECOLOGY], $activeKeys);
        $this->assertSame('Dental Plan', $company->currentSubscriptionFor($dental)->plan_name);
        $this->assertSame('Gynecology Plan', $company->currentSubscriptionFor('gynecology')->plan_name);
    }

    public function test_current_subscription_for_returns_null_when_the_company_has_no_subscription_for_that_specialty(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();

        $this->assertNull($company->currentSubscriptionFor(Specialty::GYNECOLOGY));
        $this->assertCount(0, $company->activeSpecialties());
    }

    public function test_current_subscription_for_ignores_an_expired_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();

        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $dental->id,
            'plan_name' => 'Expired Plan',
            'status' => 'active',
            'starts_at' => now()->subYear()->toDateString(),
            'ends_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertNull($company->currentSubscriptionFor($dental));
    }

    public function test_treatment_catalog_seeded_for_a_new_company_is_tagged_as_dental(): void
    {
        $company = Company::factory()->create();
        app(TreatmentCatalogSeeder::class)->seedCompany($company);

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->get();

        $this->assertGreaterThan(0, $items->count());
        $this->assertTrue($items->every(fn (TreatmentCatalog $item) => $item->specialty_id === $dental->id));
    }

    public function test_a_doctor_can_be_assigned_to_a_specialty_while_staff_stay_unassigned(): void
    {
        $company = Company::factory()->create();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();

        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'is_doctor' => false]);

        $this->assertSame($dental->id, $doctor->specialty->id);
        $this->assertNull($manager->specialty_id);
    }
}
