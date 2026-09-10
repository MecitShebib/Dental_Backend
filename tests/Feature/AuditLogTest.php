<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-AUDIT-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Audit Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_creating_a_client_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/clients', [
            'name' => 'New Patient',
            'phone' => '905550001122',
            'gender' => 'male',
        ])->assertCreated();

        $client = Client::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
        ]);
    }

    public function test_viewing_a_client_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $this->getJson("/api/clients/{$client->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'viewed',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_updating_a_client_writes_an_audit_log_with_changed_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $this->putJson("/api/clients/{$client->id}", ['city' => 'Istanbul'])->assertOk();

        $log = AuditLog::query()->where('action', 'updated')->where('auditable_id', $client->id)->firstOrFail();
        $this->assertContains('city', $log->meta['changed_fields']);
    }

    public function test_deleting_a_client_writes_an_audit_log(): void
    {
        $user = User::factory()->create();
        $client = $this->makeClient($user->company_id);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/clients/{$client->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => Client::class,
            'auditable_id' => $client->id,
        ]);
    }
}
