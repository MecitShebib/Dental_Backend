<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function makeItem(Company $company, array $overrides = []): InventoryItem
    {
        return InventoryItem::create([
            'company_id' => $company->id,
            'name' => 'Dental Gloves (Box)',
            'unit' => 'box',
            'quantity_on_hand' => 20,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_a_purchase_order_can_be_created_manually(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $item = $this->makeItem($company);

        $response = $this->postJson("/api/inventory-items/{$item->id}/purchase-orders", [
            'quantity' => 10,
            'unit_cost' => 5,
            'notes' => 'Restocking',
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'pending');
        $this->assertEquals(50.0, $response->json('data.total_cost'));
    }

    public function test_marking_an_order_received_creates_an_inventory_transaction_and_updates_stock(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);
        $item = $this->makeItem($company, ['quantity_on_hand' => 5]);

        $order = InventoryPurchaseOrder::create([
            'company_id' => $company->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'status' => InventoryPurchaseOrder::STATUS_PENDING,
        ]);

        $this->putJson("/api/inventory-purchase-orders/{$order->id}/status", ['status' => 'received'])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->assertEquals(15.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 10,
        ]);
    }

    public function test_a_received_order_cannot_be_transitioned_again(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $item = $this->makeItem($company);

        $order = InventoryPurchaseOrder::create([
            'company_id' => $company->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'status' => InventoryPurchaseOrder::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        $this->putJson("/api/inventory-purchase-orders/{$order->id}/status", ['status' => 'ordered'])
            ->assertStatus(422);
    }

    public function test_crossing_the_reorder_threshold_auto_creates_exactly_one_open_purchase_order(): void
    {
        Mail::fake();

        $company = Company::factory()->create(['email' => 'clinic@example.com']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);
        $item = $this->makeItem($company, ['quantity_on_hand' => 10, 'reorder_threshold' => 5, 'reorder_quantity' => 20]);

        $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'out', 'quantity' => 8, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseCount('inventory_purchase_orders', 1);
        $this->assertDatabaseHas('inventory_purchase_orders', [
            'inventory_item_id' => $item->id,
            'quantity' => 20,
            'status' => 'pending',
        ]);

        // Dipping further while already below threshold must not create a second order.
        $this->postJson("/api/inventory-items/{$item->id}/transactions", [
            'type' => 'out', 'quantity' => 1, 'occurred_on' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseCount('inventory_purchase_orders', 1);
    }

    public function test_purchase_orders_are_scoped_to_the_companys_own_data(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherItem = $this->makeItem($otherCompany);
        InventoryPurchaseOrder::create([
            'company_id' => $otherCompany->id, 'inventory_item_id' => $otherItem->id, 'quantity' => 5, 'status' => 'pending',
        ]);

        Sanctum::actingAs(User::factory()->create(['company_id' => $ownCompany->id]));

        $this->getJson('/api/inventory-purchase-orders')->assertOk()->assertJsonCount(0, 'data');
    }
}
