<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallLogWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Company $company): User
    {
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_admin_can_generate_a_webhook_secret(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'test-clinic']);
        Sanctum::actingAs($this->makeManager($company));

        $response = $this->postJson('/api/settings/call-webhook/regenerate')->assertOk();

        $secret = $response->json('data.webhook_secret');
        $this->assertNotEmpty($secret);
        $this->assertStringContainsString('/api/public/companies/test-clinic/calls/webhook', $response->json('data.webhook_url'));
        $this->assertSame($secret, $company->fresh()->call_webhook_secret);
    }

    public function test_webhook_rejects_a_wrong_secret(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'test-clinic', 'call_webhook_secret' => 'the-real-secret']);

        $this->postJson('/api/public/companies/test-clinic/calls/webhook', [
            'phone_number' => '905551112233',
            'direction' => 'inbound',
            'status' => 'answered',
            'occurred_at' => now()->toDateTimeString(),
            'external_id' => 'call-1',
        ], ['X-Webhook-Secret' => 'wrong-secret'])->assertStatus(401);

        $this->assertDatabaseCount('call_logs', 0);
    }

    public function test_webhook_creates_a_call_log_and_matches_an_existing_client(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'test-clinic', 'call_webhook_secret' => 'the-real-secret']);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-1001',
            'name' => 'Test Patient',
            'phone' => '+90 555 111 22 33',
            'gender' => 'male',
            'status' => 'new',
        ]);

        $response = $this->postJson('/api/public/companies/test-clinic/calls/webhook', [
            'phone_number' => '905551112233',
            'direction' => 'inbound',
            'status' => 'missed',
            'recording_url' => 'https://provider.example.com/recordings/abc.mp3',
            'occurred_at' => now()->toDateTimeString(),
            'external_id' => 'call-1',
        ], ['X-Webhook-Secret' => 'the-real-secret'])->assertCreated();

        $response->assertJsonPath('data.client_id', $client->id);
        $this->assertDatabaseHas('call_logs', ['external_id' => 'call-1', 'recording_url' => 'https://provider.example.com/recordings/abc.mp3']);
    }

    public function test_webhook_is_idempotent_on_external_id(): void
    {
        $company = Company::factory()->create(['booking_slug' => 'test-clinic', 'call_webhook_secret' => 'the-real-secret']);

        $payload = [
            'phone_number' => '905559998877',
            'direction' => 'outbound',
            'status' => 'answered',
            'occurred_at' => now()->toDateTimeString(),
            'external_id' => 'dup-call',
        ];

        $this->postJson('/api/public/companies/test-clinic/calls/webhook', $payload, ['X-Webhook-Secret' => 'the-real-secret'])->assertCreated();
        $this->postJson('/api/public/companies/test-clinic/calls/webhook', $payload, ['X-Webhook-Secret' => 'the-real-secret'])->assertCreated();

        $this->assertDatabaseCount('call_logs', 1);
    }

    public function test_a_missed_call_can_be_marked_followed_up(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));

        $log = CallLog::create(['company_id' => $company->id, 'phone_number' => '1', 'direction' => 'inbound', 'status' => 'missed', 'occurred_at' => now()]);

        $this->postJson("/api/call-logs/{$log->id}/follow-up")->assertOk()
            ->assertJsonPath('data.needs_follow_up', false);

        $this->assertNotNull($log->fresh()->followed_up_at);
    }

    public function test_summary_counts_missed_calls_needing_follow_up(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));

        CallLog::create(['company_id' => $company->id, 'phone_number' => '1', 'direction' => 'inbound', 'status' => 'missed', 'occurred_at' => now()]);
        CallLog::create(['company_id' => $company->id, 'phone_number' => '2', 'direction' => 'inbound', 'status' => 'missed', 'occurred_at' => now(), 'followed_up_at' => now()]);

        $this->getJson('/api/call-logs/summary')->assertOk()
            ->assertJsonPath('data.missed_needing_follow_up', 1);
    }
}
