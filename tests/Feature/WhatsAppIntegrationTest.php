<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.graph_base_url' => 'https://graph.facebook.com/v20.0']);
    }

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_company_admin_can_connect_whatsapp_credentials(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->getJson('/api/settings/whatsapp')->assertOk()->assertJsonPath('data', null);

        $response = $this->putJson('/api/settings/whatsapp', [
            'access_token' => 'super-secret-token-abcdef',
            'phone_number_id' => '1234567890',
        ])->assertOk();

        $response->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.phone_number_id', '1234567890')
            ->assertJsonPath('data.access_token_preview', '••••cdef');

        $this->assertDatabaseHas('whatsapp_integrations', ['company_id' => $company->id, 'phone_number_id' => '1234567890']);

        $raw = WhatsAppIntegration::query()->where('company_id', $company->id)->first();
        $this->assertSame('super-secret-token-abcdef', $raw->access_token);
    }

    public function test_whatsapp_settings_are_forbidden_without_accounting_access(): void
    {
        $company = Company::factory()->create();
        $regularUser = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($regularUser);

        $this->getJson('/api/settings/whatsapp')->assertStatus(422);
    }

    public function test_disconnecting_removes_the_integration(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $this->putJson('/api/settings/whatsapp', ['access_token' => 'tok', 'phone_number_id' => '111'])->assertOk();
        $this->deleteJson('/api/settings/whatsapp')->assertOk();

        $this->assertDatabaseMissing('whatsapp_integrations', ['company_id' => $company->id]);
    }

    public function test_the_test_message_endpoint_sends_via_the_connected_number(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        Http::fake(['https://graph.facebook.com/v20.0/*' => Http::response(['messages' => [['id' => 'wamid.abc']]], 200)]);

        $this->putJson('/api/settings/whatsapp', ['access_token' => 'tok', 'phone_number_id' => '111'])->assertOk();

        $this->postJson('/api/settings/whatsapp/test', ['phone' => '+905551112233'])
            ->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v20.0/111/messages'
            && $request->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_appointment_reminders_prefer_whatsapp_over_sms_when_connected(): void
    {
        Mail::fake();

        config([
            'services.infobip.enabled' => true,
            'services.infobip.api_key' => 'test-api-key',
            'services.infobip.base_url' => 'https://api.infobip.com',
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/*' => Http::response(['messages' => [['id' => 'wamid.abc']]], 200),
            'https://api.infobip.com/*' => Http::response([
                'messages' => [['messageId' => 'test', 'status' => ['groupId' => 1, 'groupName' => 'PENDING']]],
            ], 200),
        ]);

        $company = Company::factory()->create();
        WhatsAppIntegration::create([
            'company_id' => $company->id,
            'access_token' => 'tok',
            'phone_number_id' => '111',
            'status' => 'active',
            'connected_at' => now(),
        ]);

        $doctor = User::factory()->create(['company_id' => $company->id]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => '+905551112233',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled',
            'date' => now()->addHours(10)->toDateString(), 'start_time' => now()->addHours(10)->format('H:i:s'),
            'duration_minutes' => 30,
        ]);

        Artisan::call('appointments:send-reminders');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.infobip.com'));
    }
}
