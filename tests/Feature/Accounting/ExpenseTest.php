<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    public function test_recording_an_expense_debits_the_company_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $this->postJson('/api/expenses', [
            'category' => 'dental_supplies',
            'vendor_name' => 'Acme Dental Supplies',
            'invoice_number' => 'INV-001',
            'amount' => 250,
            'expense_date' => '2026-08-01',
            'description' => 'Composite resin restock',
        ])->assertCreated()->assertJsonPath('data.category', 'dental_supplies');

        $this->getJson('/api/fund/summary')
            ->assertOk()
            ->assertJsonPath('data.balance', -250)
            ->assertJsonPath('data.by_source.expense', -250);
    }

    public function test_an_expense_can_be_recorded_with_an_attachment(): void
    {
        Storage::fake('public');
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->post('/api/expenses', [
            'category' => 'lab_fees',
            'amount' => 400,
            'expense_date' => '2026-08-01',
            'attachment' => $file,
        ])->assertCreated();

        $path = str_replace(Storage::disk('public')->url(''), '', $response->json('data.attachment_url'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_updating_an_expense_amount_adjusts_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $expenseId = $this->postJson('/api/expenses', [
            'category' => 'rent',
            'amount' => 1000,
            'expense_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->post("/api/expenses/{$expenseId}", [
            'amount' => 1200,
        ])->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', -1200);
    }

    public function test_deleting_an_expense_removes_it_from_the_fund(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $expenseId = $this->postJson('/api/expenses', [
            'category' => 'utilities',
            'amount' => 150,
            'expense_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/expenses/{$expenseId}")->assertOk();

        $this->getJson('/api/fund/summary')->assertJsonPath('data.balance', 0);
    }

    public function test_an_expense_linked_to_a_cari_party_posts_a_debit_to_its_ledger(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $partyId = $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Acme Dental Supplies',
        ])->assertCreated()->json('data.id');

        $expenseId = $this->postJson('/api/expenses', [
            'category' => 'dental_supplies',
            'amount' => 250,
            'expense_date' => '2026-08-01',
            'description' => 'Composite resin restock',
            'cari_partyable_type' => 'cari_party',
            'cari_partyable_id' => $partyId,
            'cari_currency' => 'TRY',
        ])->assertCreated()->json('data.id');

        $summary = collect($this->getJson("/api/cari/parties/{$partyId}/summary")->assertOk()->json('data'))
            ->keyBy('currency');
        $this->assertEquals(250.0, $summary['TRY']['debit']);

        // Re-saving without a party un-links it (delete-then-repost).
        $this->post("/api/expenses/{$expenseId}", ['amount' => 250])->assertOk();

        $summary = collect($this->getJson("/api/cari/parties/{$partyId}/summary")->assertOk()->json('data'))
            ->keyBy('currency');
        $this->assertEquals(0.0, $summary['TRY']['debit']);

        // Deleting the expense also removes it if the link were still present.
        $this->deleteJson("/api/expenses/{$expenseId}")->assertOk();
        $this->getJson('/api/cari/transactions?partyable_type=cari_party&partyable_id='.$partyId)
            ->assertJsonCount(0, 'data');
    }

    public function test_a_regular_user_cannot_record_an_expense(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/expenses', [
            'category' => 'other',
            'amount' => 100,
            'expense_date' => '2026-08-01',
        ])->assertStatus(422)->assertJsonValidationErrors('user');
    }

    public function test_a_user_cannot_update_another_companys_expense(): void
    {
        $manager = $this->makeManager();
        $otherManager = $this->makeManager();

        Sanctum::actingAs($otherManager);
        $expenseId = $this->postJson('/api/expenses', [
            'category' => 'other',
            'amount' => 100,
            'expense_date' => '2026-08-01',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($manager);
        $this->post("/api/expenses/{$expenseId}", [
            'amount' => 999,
        ])->assertNotFound();
    }
}
