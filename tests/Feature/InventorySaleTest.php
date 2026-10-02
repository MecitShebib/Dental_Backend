<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\TreatmentCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventorySaleTest extends TestCase
{
    use RefreshDatabase;

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

    protected function makeItem(Company $company, array $overrides = []): InventoryItem
    {
        return InventoryItem::create([
            'company_id' => $company->id,
            'name' => 'Whitening Kit',
            'unit' => 'piece',
            'quantity_on_hand' => 10,
            'unit_cost' => 20,
            'unit_price' => 50,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_a_sale_with_no_patient_decrements_stock_and_posts_straight_to_the_fund(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $item = $this->makeItem($company);

        $response = $this->postJson('/api/inventory-sales', [
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertEquals(100.0, $response->json('data.total'));
        $this->assertTrue($response->json('data.posted_to_fund'));
        $this->assertEquals(8.0, $item->fresh()->quantity_on_hand);

        $this->assertDatabaseCount('treatment_charges', 0);
        $this->assertDatabaseHas('fund_transactions', [
            'company_id' => $company->id,
            'source_type' => 'inventory_sale',
            'amount' => 100,
        ]);
    }

    public function test_a_sale_to_a_patient_marked_paid_posts_to_the_fund_not_the_patients_debt(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $client = $this->makeClient($company);
        $item = $this->makeItem($company);

        $response = $this->postJson('/api/inventory-sales', [
            'client_id' => $client->id,
            'is_paid' => true,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertTrue($response->json('data.posted_to_fund'));
        $this->assertDatabaseCount('treatment_charges', 0);
        $this->assertDatabaseHas('fund_transactions', [
            'company_id' => $company->id,
            'source_type' => 'inventory_sale',
            'amount' => 100,
        ]);
    }

    public function test_a_sale_to_a_patient_marked_unpaid_adds_a_charge_and_never_touches_the_fund(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $client = $this->makeClient($company);
        $item = $this->makeItem($company);

        $response = $this->postJson('/api/inventory-sales', [
            'client_id' => $client->id,
            'is_paid' => false,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertFalse($response->json('data.posted_to_fund'));
        $this->assertEquals(8.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'inventory_sale',
            'amount' => 100,
        ]);
        $this->assertDatabaseCount('fund_transactions', 0);
    }

    public function test_multiple_items_in_one_unpaid_sale_are_summed_into_the_patients_charges(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $client = $this->makeClient($company);
        $itemA = $this->makeItem($company, ['name' => 'Kit A', 'unit_price' => 50]);
        $itemB = $this->makeItem($company, ['name' => 'Kit B', 'unit_price' => 30]);

        $this->postJson('/api/inventory-sales', [
            'client_id' => $client->id,
            'is_paid' => false,
            'items' => [
                ['inventory_item_id' => $itemA->id, 'quantity' => 1],
                ['inventory_item_id' => $itemB->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertDatabaseCount('treatment_charges', 2);
        $this->assertEquals(110.0, TreatmentCharge::where('client_id', $client->id)->sum('amount'));
    }

    public function test_selling_more_than_the_stock_on_hand_is_rejected(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $item = $this->makeItem($company, ['quantity_on_hand' => 1]);

        $this->postJson('/api/inventory-sales', [
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5],
            ],
        ])->assertUnprocessable();

        $this->assertEquals(1.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('treatment_charges', 0);
        $this->assertDatabaseCount('fund_transactions', 0);
    }

    public function test_a_client_from_another_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $otherClient = $this->makeClient($otherCompany);
        $item = $this->makeItem($company);

        $this->postJson('/api/inventory-sales', [
            'client_id' => $otherClient->id,
            'is_paid' => true,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }
}
