<?php

namespace Tests\Feature\Accounting;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CariTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    protected function makeParty(): int
    {
        return $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Test Supplier',
        ])->assertCreated()->json('data.id');
    }

    public function test_manual_debit_and_credit_entries_compute_a_correct_balance(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $partyId = $this->makeParty();

        $this->postJson('/api/cari/transactions', [
            'partyable_type' => 'cari_party',
            'partyable_id' => $partyId,
            'invoice_date' => '2026-08-01',
            'description' => 'Invoice #1',
            'debit' => 1000,
            'currency' => 'TRY',
            'transaction_type' => 'invoice',
        ])->assertCreated();

        $this->postJson('/api/cari/transactions', [
            'partyable_type' => 'cari_party',
            'partyable_id' => $partyId,
            'payment_date' => '2026-08-05',
            'description' => 'Payment #1',
            'credit' => 400,
            'currency' => 'TRY',
            'transaction_type' => 'payment',
        ])->assertCreated();

        $summary = $this->getJson("/api/cari/parties/{$partyId}/summary")->assertOk()->json('data');
        $tryRow = collect($summary)->firstWhere('currency', 'TRY');

        $this->assertEquals(1000.0, $tryRow['debit']);
        $this->assertEquals(400.0, $tryRow['credit']);
        $this->assertEquals(600.0, $tryRow['balance']);
        $this->assertSame('debtor', $tryRow['status']);

        $this->getJson('/api/cari/transactions?partyable_type=cari_party&partyable_id='.$partyId)
            ->assertJsonCount(2, 'data');
    }

    public function test_usd_and_try_balances_are_tracked_separately(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $partyId = $this->makeParty();

        $this->postJson('/api/cari/transactions', [
            'partyable_type' => 'cari_party',
            'partyable_id' => $partyId,
            'debit' => 100,
            'currency' => 'USD',
            'exchange_rate' => 34.5,
            'transaction_type' => 'invoice',
        ])->assertCreated();

        $this->postJson('/api/cari/transactions', [
            'partyable_type' => 'cari_party',
            'partyable_id' => $partyId,
            'debit' => 500,
            'currency' => 'TRY',
            'transaction_type' => 'invoice',
        ])->assertCreated();

        $summary = collect($this->getJson("/api/cari/parties/{$partyId}/summary")->assertOk()->json('data'))
            ->keyBy('currency');

        $this->assertEquals(100.0, $summary['USD']['debit']);
        $this->assertEquals(500.0, $summary['TRY']['debit']);
    }

    public function test_a_manual_transaction_can_be_updated_and_deleted(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);
        $partyId = $this->makeParty();

        $transactionId = $this->postJson('/api/cari/transactions', [
            'partyable_type' => 'cari_party',
            'partyable_id' => $partyId,
            'debit' => 100,
            'currency' => 'TRY',
            'transaction_type' => 'invoice',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/cari/transactions/{$transactionId}", [
            'debit' => 250,
            'currency' => 'TRY',
            'transaction_type' => 'invoice',
        ])->assertOk()->assertJsonPath('data.debit', 250);

        $this->deleteJson("/api/cari/transactions/{$transactionId}")->assertOk();
        $this->getJson('/api/cari/transactions?partyable_type=cari_party&partyable_id='.$partyId)
            ->assertJsonCount(0, 'data');
    }

    public function test_an_automatically_posted_entry_cannot_be_edited_or_deleted_directly(): void
    {
        $company = Company::factory()->create();
        $manager = $this->makeManager($company);
        Sanctum::actingAs($manager);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $labPartnerId = $this->postJson('/api/lab-partners', ['name' => 'Test Lab'])->assertCreated()->json('data.id');

        $this->postJson("/api/clients/{$client->id}/lab-cases", [
            'doctor_id' => $doctor->id,
            'lab_partner_id' => $labPartnerId,
            'work_type' => 'crown',
            'sent_date' => '2026-08-01',
            'lab_cost' => 1000,
        ])->assertCreated();

        $transactionId = $this->getJson('/api/cari/transactions?partyable_type=lab_partner&partyable_id='.$labPartnerId)
            ->assertOk()->json('data.0.id');

        $this->putJson("/api/cari/transactions/{$transactionId}", [
            'debit' => 999,
            'currency' => 'TRY',
            'transaction_type' => 'invoice',
        ])->assertStatus(422)->assertJsonValidationErrors('source_type');

        $this->deleteJson("/api/cari/transactions/{$transactionId}")
            ->assertStatus(422)->assertJsonValidationErrors('source_type');
    }
}
