<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.infobip.enabled' => true,
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.base_url' => 'https://api.infobip.com',
        ]);

        Http::fake([
            'https://api.infobip.com/*' => Http::response([
                'messages' => [['messageId' => 'test', 'status' => ['groupId' => 1, 'groupName' => 'PENDING']]],
            ], 200),
        ]);
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_index_lists_every_template_slot_with_defaults_when_uncustomized(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/settings/message-templates')->assertOk();
        $rows = collect($response->json('data'));

        $smsReminder = $rows->firstWhere(fn ($row) => $row['key'] === 'appointment_reminder' && $row['channel'] === 'sms' && $row['language'] === 'en');

        $this->assertNotNull($smsReminder);
        $this->assertFalse($smsReminder['is_custom']);
        $this->assertNull($smsReminder['body']);
        $this->assertStringContainsString('{doctor_name}', $smsReminder['default_body']);
        $this->assertContains('doctor_name', $smsReminder['placeholders']);
    }

    public function test_message_templates_are_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/settings/message-templates')->assertStatus(422);
    }

    public function test_a_custom_template_can_be_saved_and_is_reflected_in_the_index(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->putJson('/api/settings/message-templates', [
            'key' => 'appointment_reminder',
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Custom reminder for {client_name} at {company_name}.',
        ])->assertOk();

        $rows = collect($this->getJson('/api/settings/message-templates')->json('data'));
        $row = $rows->firstWhere(fn ($r) => $r['key'] === 'appointment_reminder' && $r['channel'] === 'sms' && $r['language'] === 'en');

        $this->assertTrue($row['is_custom']);
        $this->assertSame('Custom reminder for {client_name} at {company_name}.', $row['body']);
    }

    public function test_saving_a_blank_template_reverts_to_the_default(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        MessageTemplate::create([
            'company_id' => $company->id, 'key' => 'appointment_reminder', 'channel' => 'sms', 'language' => 'en',
            'body' => 'Custom text',
        ]);

        $this->putJson('/api/settings/message-templates', [
            'key' => 'appointment_reminder', 'channel' => 'sms', 'language' => 'en',
        ])->assertOk();

        $this->assertDatabaseMissing('message_templates', ['company_id' => $company->id, 'key' => 'appointment_reminder']);
    }

    public function test_a_custom_appointment_reminder_sms_template_is_actually_sent(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        MessageTemplate::create([
            'company_id' => $company->id, 'key' => 'appointment_reminder', 'channel' => 'sms', 'language' => 'en',
            'body' => 'CUSTOM WORDING for {client_name} with Dr. {doctor_name}.',
        ]);

        $doctor = User::factory()->create(['company_id' => $company->id, 'name' => 'Ali Doctor']);
        $client = Client::create([
            'company_id' => $company->id, 'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient', 'phone' => fake()->unique()->e164PhoneNumber(), 'gender' => 'male',
            'status' => 'new', 'preferred_language' => 'en',
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled',
            'date' => now()->addHours(10)->toDateString(), 'start_time' => now()->addHours(10)->format('H:i:s'),
            'duration_minutes' => 30,
        ]);

        Artisan::call('appointments:send-reminders');

        Http::assertSent(fn ($request) => str_contains((string) $request['messages'][0]['text'], 'CUSTOM WORDING for Test Patient with Dr. Ali Doctor.'));
    }
}
