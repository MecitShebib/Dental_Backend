<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\TreatmentCatalog;
use App\Models\TreatmentCatalogInventoryLink;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTreatmentConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(Company $company, string $code): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => $code,
            'name' => 'Nour',
            'phone' => '+963900'.random_int(100000, 999999),
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function makeLinkedSetup(): array
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $item = InventoryItem::create([
            'company_id' => $company->id, 'name' => 'Composite Material', 'unit' => 'g', 'quantity_on_hand' => 100, 'status' => 'active',
        ]);
        $catalogEntry = TreatmentCatalog::create([
            'company_id' => $company->id, 'scope' => 'company', 'code' => 'filling', 'name_en' => 'Filling', 'name_ar' => 'حشوة', 'default_price' => 1500,
        ]);
        TreatmentCatalogInventoryLink::create([
            'treatment_catalog_id' => $catalogEntry->id, 'inventory_item_id' => $item->id, 'quantity_per_use' => 2,
        ]);

        $client = $this->makeClient($company, 'CL-6001');

        return compact('company', 'doctor', 'item', 'catalogEntry', 'client');
    }

    public function test_creating_a_visit_with_a_catalog_linked_item_auto_consumes_inventory(): void
    {
        ['doctor' => $doctor, 'item' => $item, 'catalogEntry' => $catalogEntry, 'client' => $client] = $this->makeLinkedSetup();

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();

        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id, 'type' => 'out', 'quantity' => 2, 'is_auto_consumption' => 1,
        ]);
    }

    public function test_resaving_the_same_visit_with_the_same_item_does_not_double_consume(): void
    {
        ['doctor' => $doctor, 'item' => $item, 'catalogEntry' => $catalogEntry, 'client' => $client] = $this->makeLinkedSetup();

        $response = $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();
        $visitId = $response->json('data.id');

        $this->putJson("/api/visits/{$visitId}", [
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertOk();

        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_removing_the_catalog_item_on_resync_restocks_it(): void
    {
        ['doctor' => $doctor, 'item' => $item, 'catalogEntry' => $catalogEntry, 'client' => $client] = $this->makeLinkedSetup();

        $response = $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();
        $visitId = $response->json('data.id');
        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);

        $this->putJson("/api/visits/{$visitId}", [
            'charge_items' => [
                ['description' => 'Consultation only', 'amount' => 500],
            ],
        ])->assertOk();

        $this->assertEquals(100.0, $item->fresh()->quantity_on_hand);
    }

    public function test_deleting_a_visit_restocks_its_auto_consumed_inventory(): void
    {
        ['doctor' => $doctor, 'item' => $item, 'catalogEntry' => $catalogEntry, 'client' => $client] = $this->makeLinkedSetup();

        $response = $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();
        $visitId = $response->json('data.id');
        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);

        $this->deleteJson("/api/visits/{$visitId}")->assertOk();

        $this->assertEquals(100.0, $item->fresh()->quantity_on_hand);
    }

    public function test_a_manual_charge_item_without_a_catalog_id_never_touches_inventory(): void
    {
        ['doctor' => $doctor, 'item' => $item, 'client' => $client] = $this->makeLinkedSetup();

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Custom fee', 'amount' => 200],
            ],
        ])->assertCreated();

        $this->assertEquals(100.0, $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_auto_consumption_clamps_at_zero_instead_of_blocking_the_visit(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $item = InventoryItem::create([
            'company_id' => $company->id, 'name' => 'Rare Material', 'unit' => 'g', 'quantity_on_hand' => 1, 'status' => 'active',
        ]);
        $catalogEntry = TreatmentCatalog::create([
            'company_id' => $company->id, 'scope' => 'company', 'code' => 'implant', 'name_en' => 'Implant', 'name_ar' => 'زراعة', 'default_price' => 3000,
        ]);
        TreatmentCatalogInventoryLink::create([
            'treatment_catalog_id' => $catalogEntry->id, 'inventory_item_id' => $item->id, 'quantity_per_use' => 5,
        ]);
        $client = $this->makeClient($company, 'CL-6002');

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Implant', 'amount' => 3000, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();

        $this->assertEquals(0.0, $item->fresh()->quantity_on_hand);
    }

    public function test_a_treatment_catalog_id_from_another_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($company, 'CL-6003');

        $otherCompany = Company::factory()->create();
        $otherCatalogEntry = TreatmentCatalog::create([
            'company_id' => $otherCompany->id, 'scope' => 'company', 'code' => 'x', 'name_en' => 'X', 'name_ar' => 'X', 'default_price' => 100,
        ]);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'X', 'amount' => 100, 'treatment_catalog_id' => $otherCatalogEntry->id],
            ],
        ])->assertStatus(422);
    }

    protected function doctorWithFullWeekSchedule(Company $company): User
    {
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30,
        ]);
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    public function test_checking_in_an_appointment_retargets_consumption_without_double_consuming(): void
    {
        $company = Company::factory()->create();
        $doctor = $this->doctorWithFullWeekSchedule($company);
        Sanctum::actingAs($doctor);

        $item = InventoryItem::create([
            'company_id' => $company->id, 'name' => 'Composite Material', 'unit' => 'g', 'quantity_on_hand' => 100, 'status' => 'active',
        ]);
        $catalogEntry = TreatmentCatalog::create([
            'company_id' => $company->id, 'scope' => 'company', 'code' => 'filling', 'name_en' => 'Filling', 'name_ar' => 'حشوة', 'default_price' => 1500,
        ]);
        TreatmentCatalogInventoryLink::create([
            'treatment_catalog_id' => $catalogEntry->id, 'inventory_item_id' => $item->id, 'quantity_per_use' => 2,
        ]);
        $client = $this->makeClient($company, 'CL-6004');

        $appointmentResponse = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'charge_items' => [
                ['description' => 'Filling', 'amount' => 1500, 'treatment_catalog_id' => $catalogEntry->id],
            ],
        ])->assertCreated();
        $appointmentId = $appointmentResponse->json('data.id');

        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);

        // Check in without changing charge_items -- the retargeted
        // consumption row must carry over as-is, not be re-applied.
        $this->postJson("/api/appointments/{$appointmentId}/check-in")->assertOk();

        $this->assertEquals(98.0, $item->fresh()->quantity_on_hand);
        $this->assertSame(1, InventoryTransaction::query()->where('inventory_item_id', $item->id)->count());

        $visitId = Visit::query()->where('client_id', $client->id)->firstOrFail()->id;
        $this->assertDatabaseHas('treatment_charge_inventory_consumptions', [
            'source_type' => 'visit', 'source_id' => $visitId, 'inventory_item_id' => $item->id, 'quantity' => 2,
        ]);
    }

    public function test_inventory_links_can_be_managed_for_a_catalog_entry(): void
    {
        ['item' => $item, 'catalogEntry' => $catalogEntry] = $this->makeLinkedSetup();

        $secondItem = InventoryItem::create([
            'company_id' => $catalogEntry->company_id, 'name' => 'Anesthetic', 'unit' => 'ml', 'quantity_on_hand' => 50, 'status' => 'active',
        ]);

        $this->putJson("/api/treatment-catalog/{$catalogEntry->id}/inventory-links", [
            'links' => [
                ['inventory_item_id' => $item->id, 'quantity_per_use' => 3],
                ['inventory_item_id' => $secondItem->id, 'quantity_per_use' => 1],
            ],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('treatment_catalog_inventory_links', 2);
        $this->assertDatabaseHas('treatment_catalog_inventory_links', [
            'treatment_catalog_id' => $catalogEntry->id, 'inventory_item_id' => $item->id, 'quantity_per_use' => 3,
        ]);

        $this->getJson("/api/treatment-catalog/{$catalogEntry->id}/inventory-links")->assertOk()->assertJsonCount(2, 'data');
    }
}
