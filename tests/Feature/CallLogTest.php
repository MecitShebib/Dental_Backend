<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(Company $company, string $phone): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => $phone,
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_a_call_can_be_logged_and_auto_matched_to_a_known_client(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $client = $this->makeClient($company, '+90 555 111 22 33');

        $response = $this->postJson('/api/call-logs', [
            'phone_number' => '905551112233',
            'direction' => 'inbound',
            'status' => 'missed',
            'occurred_at' => now()->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('data.client_id', $client->id);
        $response->assertJsonPath('data.client_name', 'Test Patient');
    }

    public function test_a_call_from_an_unknown_number_is_logged_without_a_client(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/call-logs', [
            'phone_number' => '905559998877',
            'direction' => 'outbound',
            'status' => 'answered',
            'duration_seconds' => 120,
            'occurred_at' => now()->toDateTimeString(),
        ])->assertCreated();

        $this->assertNull($response->json('data.client_id'));
    }

    public function test_call_logs_are_scoped_to_the_companys_own_data(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        CallLog::create([
            'company_id' => $otherCompany->id, 'phone_number' => '123', 'direction' => 'inbound',
            'status' => 'missed', 'occurred_at' => now(),
        ]);

        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/call-logs')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_summary_counts_calls_by_direction_and_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        CallLog::create(['company_id' => $company->id, 'phone_number' => '1', 'direction' => 'inbound', 'status' => 'missed', 'occurred_at' => now()]);
        CallLog::create(['company_id' => $company->id, 'phone_number' => '2', 'direction' => 'inbound', 'status' => 'answered', 'occurred_at' => now()]);
        CallLog::create(['company_id' => $company->id, 'phone_number' => '3', 'direction' => 'outbound', 'status' => 'answered', 'occurred_at' => now()]);

        $response = $this->getJson('/api/call-logs/summary')->assertOk();

        $response->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.inbound', 2)
            ->assertJsonPath('data.outbound', 1)
            ->assertJsonPath('data.missed', 1)
            ->assertJsonPath('data.answered', 2);
    }

    public function test_a_call_log_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $log = CallLog::create(['company_id' => $company->id, 'phone_number' => '1', 'direction' => 'inbound', 'status' => 'missed', 'occurred_at' => now()]);

        $this->deleteJson("/api/call-logs/{$log->id}")->assertOk();
        $this->assertDatabaseMissing('call_logs', ['id' => $log->id]);
    }
}
