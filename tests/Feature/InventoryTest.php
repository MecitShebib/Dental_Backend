<?php

namespace Tests\Feature;

use App\Mail\LowStockAlertMail;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_can_be_created_and_listed(): void
    {
        $company = Company::factory()->create(['email' => 'clinic@example.com']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/inventory-items', [
            'name' => 'Composite Resin',
            'unit' => 'box',
            'quantity_on_hand' => 10,
            'reorder_threshold' => 3,
            'supplier_name' => 'Dental Supplies Co.',
            'supplier_contact' => '+90 555 000 11 22',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Composite Resin')
            ->assertJsonPath('data.supplier_name', 'Dental Supplies Co.')
            ->assertJsonPath('data.supplier_contact', '+90 555 000 11 22');

        $this->getJson('/api/inventory-items')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_list_can_be_filtered_by_name_and_by_branch(): void
    {
        $company = Company::factory()->create();
        $branchA = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Branch B', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        InventoryItem::create(['company_id' => $company->id, 'branch_id' => $branchA->id, 'name' => 'Composite Resin', 'quantity_on_hand' => 5]);
        InventoryItem::create(['company_id' => $company->id, 'branch_id' => $branchB->id, 'name' => 'Disposable Gloves', 'quantity_on_hand' => 10]);

        $this->getJson('/api/inventory-items?name=resin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Composite Resin');

        $response = $this->getJson("/api/inventory-items?branch_id={$branchB->id}")->assertOk();
        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Disposable Gloves')
            ->assertJsonPath('data.0.branch_name', 'Branch B');
    }

    public function test_items_are_scoped_to_the_companys_own_data(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        InventoryItem::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Item', 'quantity_on_hand' => 5]);

        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/inventory-items')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_stock_in_transaction_increases_quantity_on_hand(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $item = InventoryItem::create(['company_id' => $company->id, 'name' => 'Gloves', 'quantity_on_hand' => 10]);

        $response = $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'in', 'quantity' => 50, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertEquals(60.0, $response->json('data.item.quantity_on_hand'));
    }

    public function test_a_stock_out_transaction_cannot_exceed_quantity_on_hand(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $item = InventoryItem::create(['company_id' => $company->id, 'name' => 'Anesthetic', 'quantity_on_hand' => 5]);

        $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'out', 'quantity' => 10, 'occurred_on' => now()->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(5.0, (float) $item->fresh()->quantity_on_hand);
    }

    public function test_low_stock_filter_only_returns_items_at_or_below_threshold(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        InventoryItem::create(['company_id' => $company->id, 'name' => 'Low Item', 'quantity_on_hand' => 2, 'reorder_threshold' => 5]);
        InventoryItem::create(['company_id' => $company->id, 'name' => 'Fine Item', 'quantity_on_hand' => 20, 'reorder_threshold' => 5]);
        InventoryItem::create(['company_id' => $company->id, 'name' => 'No Threshold Item', 'quantity_on_hand' => 1]);

        $response = $this->getJson('/api/inventory-items?low_stock=1')->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Low Item');
    }

    public function test_crossing_below_the_reorder_threshold_sends_exactly_one_alert_email(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['email' => 'clinic@example.com']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $item = InventoryItem::create(['company_id' => $company->id, 'name' => 'Sutures', 'quantity_on_hand' => 10, 'reorder_threshold' => 5]);

        // Crosses 10 -> 4 (below threshold): should alert.
        $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'out', 'quantity' => 6, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        // Still below threshold (4 -> 2): should NOT alert again.
        $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'out', 'quantity' => 2, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        Mail::assertSent(LowStockAlertMail::class, 1);
    }

    public function test_an_adjustment_transaction_applies_a_signed_delta(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $item = InventoryItem::create(['company_id' => $company->id, 'name' => 'Masks', 'quantity_on_hand' => 100]);

        $response = $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'adjustment', 'quantity' => -15, 'occurred_on' => now()->toDateString(), 'reason' => 'Recount',
        ])->assertCreated();

        $this->assertEquals(85.0, $response->json('data.item.quantity_on_hand'));
    }
}
