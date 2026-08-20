<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialtyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_it_lists_all_five_specialties_flagged_with_subscription_and_build_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/specialties')->assertOk();

        $response->assertJsonCount(5, 'data');

        $dental = collect($response->json('data'))->firstWhere('key', Specialty::DENTAL);
        $this->assertSame('Dentavaria', $dental['brand_name']);
        $this->assertTrue($dental['is_built']);
        // Company::factory() auto-attaches a dental subscription.
        $this->assertTrue($dental['is_subscribed']);

        $gynecology = collect($response->json('data'))->firstWhere('key', Specialty::GYNECOLOGY);
        $this->assertSame('Gynevaria', $gynecology['brand_name']);
        // GynecologyModule::isBuilt() flipped to true once the prenatal care plan frontend shipped.
        $this->assertTrue($gynecology['is_built']);
        $this->assertFalse($gynecology['is_subscribed']);
    }

    public function test_a_specialty_the_company_actively_subscribes_to_is_flagged_subscribed(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $gynecology->id,
            'plan_name' => 'Gynecology Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/specialties')->assertOk();

        $gynecologyRow = collect($response->json('data'))->firstWhere('key', Specialty::GYNECOLOGY);
        $this->assertTrue($gynecologyRow['is_subscribed']);

        $dentalRow = collect($response->json('data'))->firstWhere('key', Specialty::DENTAL);
        $this->assertFalse($dentalRow['is_subscribed']);
    }
}
