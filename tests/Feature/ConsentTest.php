<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConsentTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    // A tiny valid 1x1 transparent PNG, base64-encoded.
    protected const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_manager_can_create_a_consent_template(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->postJson('/api/consent-templates', [
            'title' => 'General Treatment Consent',
            'body' => 'I, {client_name}, consent to treatment at {company_name} on {date}.',
            'language' => 'en',
        ])->assertCreated()->assertJsonPath('data.title', 'General Treatment Consent');
    }

    public function test_a_regular_user_cannot_create_a_consent_template(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/consent-templates', [
            'title' => 'General Treatment Consent',
            'body' => 'Body text',
            'language' => 'en',
        ])->assertStatus(422);
    }

    public function test_a_client_can_sign_a_consent_and_the_text_is_frozen_at_signing_time(): void
    {
        $company = Company::factory()->create(['name' => 'Verify Clinic']);
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company);
        $template = ConsentTemplate::create([
            'company_id' => $company->id,
            'title' => 'General Consent',
            'body' => 'I, {client_name}, consent to treatment at {company_name}.',
            'language' => 'en',
        ]);

        $response = $this->postJson("/api/clients/{$client->id}/consents", [
            'consent_template_id' => $template->id,
            'signature' => 'data:image/png;base64,'.self::TINY_PNG_BASE64,
        ])->assertCreated();

        $response->assertJsonPath('data.body', 'I, Test Patient, consent to treatment at Verify Clinic.');
        $signatureUrl = $response->json('data.signature_url');
        $this->assertNotEmpty($signatureUrl);

        // Editing the template afterwards must not change the already-signed record.
        $template->update(['body' => 'A completely different body.']);
        $stored = $client->consents()->first();
        $this->assertSame('I, Test Patient, consent to treatment at Verify Clinic.', $stored->body);
    }

    public function test_signing_rejects_a_non_image_payload(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company);
        $template = ConsentTemplate::create([
            'company_id' => $company->id, 'title' => 'General Consent', 'body' => 'Body', 'language' => 'en',
        ]);

        $this->postJson("/api/clients/{$client->id}/consents", [
            'consent_template_id' => $template->id,
            'signature' => 'not-a-data-url',
        ])->assertStatus(422);
    }

    public function test_a_template_can_have_structured_sections_and_they_are_frozen_at_signing_time(): void
    {
        $company = Company::factory()->create(['name' => 'Verify Clinic']);
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company);
        $template = ConsentTemplate::create([
            'company_id' => $company->id,
            'title' => 'Detailed Consent',
            'body' => 'Main body for {client_name}.',
            'sections' => [
                ['heading' => 'Risks', 'body' => 'The risks for {client_name} include...'],
                ['heading' => 'Alternatives', 'body' => 'Alternative treatments at {company_name}...'],
            ],
            'language' => 'en',
        ]);

        $response = $this->postJson("/api/clients/{$client->id}/consents", [
            'consent_template_id' => $template->id,
            'signature' => 'data:image/png;base64,'.self::TINY_PNG_BASE64,
        ])->assertCreated();

        $response->assertJsonPath('data.sections.0.heading', 'Risks')
            ->assertJsonPath('data.sections.0.body', 'The risks for Test Patient include...')
            ->assertJsonPath('data.sections.1.body', 'Alternative treatments at Verify Clinic...');

        // Editing the template's sections afterwards must not change the signed record.
        $template->update(['sections' => [['heading' => 'Changed', 'body' => 'Changed body.']]]);
        $stored = $client->consents()->first();
        $this->assertSame('Risks', $stored->sections[0]['heading']);
    }

    public function test_a_template_with_no_sections_freezes_a_null_sections_value(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $client = $this->makeClient($company);
        $template = ConsentTemplate::create([
            'company_id' => $company->id, 'title' => 'Simple Consent', 'body' => 'Body', 'language' => 'en',
        ]);

        $response = $this->postJson("/api/clients/{$client->id}/consents", [
            'consent_template_id' => $template->id,
            'signature' => 'data:image/png;base64,'.self::TINY_PNG_BASE64,
        ])->assertCreated();

        $response->assertJsonPath('data.sections', []);
    }

    public function test_consents_are_scoped_to_the_companys_own_clients(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $otherClient = $this->makeClient($otherCompany);
        $otherTemplate = ConsentTemplate::create(['company_id' => $otherCompany->id, 'title' => 'X', 'body' => 'Y', 'language' => 'en']);
        app(ConsentService::class)->sign($otherClient, $otherTemplate, 'data:image/png;base64,'.self::TINY_PNG_BASE64, null, null, null);

        $ownManager = $this->makeManager($ownCompany);
        Sanctum::actingAs($ownManager);

        $this->getJson("/api/clients/{$otherClient->id}/consents")->assertNotFound();
    }
}
