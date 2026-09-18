<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ConsentTemplate;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentTemplateSpecialtyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_consent_template_can_be_created_with_a_specialty(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $specialtyId = Specialty::query()->where('key', Specialty::NUTRITION)->value('id');

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/consent-templates', [
            'specialty_id' => $specialtyId,
            'title' => 'Nutrition-specific Consent',
            'body' => 'Consent body text.',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_id', $specialtyId);
        $this->assertDatabaseHas('consent_templates', ['title' => 'Nutrition-specific Consent', 'specialty_id' => $specialtyId]);
    }

    public function test_a_consent_template_can_be_created_without_a_specialty(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/consent-templates', [
            'title' => 'Company-wide Consent',
            'body' => 'Consent body text.',
            'language' => 'en',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_id', null);
    }

    public function test_the_index_scopes_to_the_requested_specialty_but_keeps_unassigned_templates_visible(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $nutritionId = Specialty::query()->where('key', Specialty::NUTRITION)->value('id');
        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');

        ConsentTemplate::create(['company_id' => $company->id, 'specialty_id' => $nutritionId, 'title' => 'Nutrition Only', 'body' => 'Body', 'language' => 'en', 'is_active' => true]);
        ConsentTemplate::create(['company_id' => $company->id, 'specialty_id' => $dentalId, 'title' => 'Dental Only', 'body' => 'Body', 'language' => 'en', 'is_active' => true]);
        ConsentTemplate::create(['company_id' => $company->id, 'specialty_id' => null, 'title' => 'Applies Everywhere', 'body' => 'Body', 'language' => 'en', 'is_active' => true]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/consent-templates?specialty=nutrition')->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Applies Everywhere', 'Nutrition Only'], $titles);
    }

    public function test_a_consent_templates_specialty_can_be_updated(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $specialtyId = Specialty::query()->where('key', Specialty::GYNECOLOGY)->value('id');
        $template = ConsentTemplate::create([
            'company_id' => $company->id,
            'title' => 'Existing',
            'body' => 'Body',
            'language' => 'en',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/consent-templates/{$template->id}", [
            'specialty_id' => $specialtyId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.specialty_id', $specialtyId);
    }
}
