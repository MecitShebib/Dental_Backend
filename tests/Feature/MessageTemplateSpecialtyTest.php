<?php

namespace Tests\Feature;

use App\Enums\ClientLanguage;
use App\Models\Company;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MessageTemplateService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTemplateSpecialtyTest extends TestCase
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

    public function test_a_specialty_specific_override_can_be_saved_and_is_scoped_to_that_specialty(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        $specialtyId = Specialty::query()->where('key', Specialty::NUTRITION)->value('id');

        Sanctum::actingAs($manager);

        $response = $this->putJson('/api/settings/message-templates', [
            'specialty_id' => $specialtyId,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Nutrition-specific reminder text.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.specialty_id', $specialtyId);

        $this->assertDatabaseHas('message_templates', [
            'company_id' => $company->id,
            'specialty_id' => $specialtyId,
            'key' => 'appointment_reminder',
            'body' => 'Nutrition-specific reminder text.',
        ]);

        // Fetching the company-wide view (no specialty) should not surface the specialty-specific row.
        $companyWide = $this->getJson('/api/settings/message-templates');
        $companyWide->assertOk();
        $companyWideRow = collect($companyWide->json('data'))
            ->firstWhere(fn ($row) => $row['key'] === 'appointment_reminder' && $row['channel'] === 'sms' && $row['language'] === 'en');
        $this->assertFalse($companyWideRow['is_custom']);

        // Fetching the specialty-scoped view should surface it.
        $specialtyScoped = $this->getJson('/api/settings/message-templates?specialty=nutrition');
        $specialtyScoped->assertOk();
        $specialtyScopedRow = collect($specialtyScoped->json('data'))
            ->firstWhere(fn ($row) => $row['key'] === 'appointment_reminder' && $row['channel'] === 'sms' && $row['language'] === 'en');
        $this->assertTrue($specialtyScopedRow['is_custom']);
        $this->assertSame('Nutrition-specific reminder text.', $specialtyScopedRow['body']);
    }

    public function test_render_falls_back_from_specialty_specific_to_company_wide_to_default(): void
    {
        $company = Company::factory()->create();
        $nutritionId = Specialty::query()->where('key', Specialty::NUTRITION)->value('id');
        $dentalId = Specialty::query()->where('key', Specialty::DENTAL)->value('id');

        $service = app(MessageTemplateService::class);

        $default = $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $nutritionId);
        $this->assertNotEmpty($default['body']);

        MessageTemplate::create([
            'company_id' => $company->id,
            'specialty_id' => null,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Company-wide text.',
        ]);

        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $nutritionId)['body']);
        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $dentalId)['body']);

        MessageTemplate::create([
            'company_id' => $company->id,
            'specialty_id' => $nutritionId,
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Nutrition-only text.',
        ]);

        $this->assertSame('Nutrition-only text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $nutritionId)['body']);
        $this->assertSame('Company-wide text.', $service->render($company, 'appointment_reminder', 'sms', ClientLanguage::English, [], $dentalId)['body']);
    }
}
