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

    public function test_selling_items_to_a_patient_decrements_stock_and_adds_a_charge(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $client = $this->makeClient($company);
        $item = $this->makeItem($company);

        $response = $this->postJson("/api/clients/{$client->id}/inventory-sales", [
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->assertEquals(100.0, $response->json('data.total'));
        $this->assertEquals(8.0, $item->fresh()->quantity_on_hand);

        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'inventory_sale',
            'amount' => 100,
        ]);
    }

    public function test_multiple_items_in_one_sale_are_summed_into_the_patients_charges(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));
        $client = $this->makeClient($company);
        $itemA = $this->makeItem($company, ['name' => 'Kit A', 'unit_price' => 50]);
        $itemB = $this->makeItem($company, ['name' => 'Kit B', 'unit_price' => 30]);

        $this->postJson("/api/clients/{$client->id}/inventory-sales", [
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
        $client = $this->makeClient($company);
        $item = $this->makeItem($company, ['quantity_on_hand' => 1]);

        $this->postJson("/api/clients/{$client->id}/inventory-sales", [
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5],
            ],
        ])->assertUnprocessable();

        $this->assertEquals(1.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('treatment_charges', 0);
    }
}
