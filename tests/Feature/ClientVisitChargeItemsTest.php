<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientVisitChargeItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(string $code = 'CL-5201'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Nour',
            'phone' => '+963900005201',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_creating_a_visit_stores_one_charge_row_per_line_item(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $response = $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'charge_items' => [
                ['description' => 'Composite filling', 'amount' => 1500],
                ['description' => 'Discount', 'amount' => -100],
            ],
        ])->assertCreated();

        $visitId = $response->json('data.id');

        $this->assertDatabaseCount('treatment_charges', 2);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'visit',
            'source_id' => $visitId,
            'amount' => 1500,
            'description' => 'Composite filling',
        ]);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'visit',
            'source_id' => $visitId,
            'amount' => -100,
            'description' => 'Discount',
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.financial_summary.total_services_amount', 1400);
    }

    public function test_updating_a_visits_charge_items_replaces_the_previous_set(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-5202');

        $visit = Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'attendance_status' => 'walk_in',
        ]);
        $client->treatmentCharges()->create(['source_type' => 'visit', 'source_id' => $visit->id, 'amount' => 500, 'description' => 'Old item']);

        $this->putJson("/api/visits/{$visit->id}", [
            'charge_items' => [
                ['description' => 'New item', 'amount' => 800],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('treatment_charges', 1);
        $this->assertDatabaseHas('treatment_charges', [
            'source_type' => 'visit',
            'source_id' => $visit->id,
            'amount' => 800,
            'description' => 'New item',
        ]);
        $this->assertDatabaseMissing('treatment_charges', ['description' => 'Old item']);
    }

    public function test_updating_a_visit_without_charge_items_leaves_existing_charges_untouched(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-5203');

        $visit = Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'attendance_status' => 'walk_in',
        ]);
        $client->treatmentCharges()->create(['source_type' => 'visit', 'source_id' => $visit->id, 'amount' => 500, 'description' => 'Kept item']);

        $this->putJson("/api/visits/{$visit->id}", [
            'notes' => 'Just updating notes',
        ])->assertOk();

        $this->assertDatabaseHas('treatment_charges', [
            'source_type' => 'visit',
            'source_id' => $visit->id,
            'amount' => 500,
            'description' => 'Kept item',
        ]);
    }

    public function test_deleting_a_visit_removes_its_charge_items(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-5204');

        $visit = Visit::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->toDateString(),
            'attendance_status' => 'walk_in',
        ]);
        $client->treatmentCharges()->create(['source_type' => 'visit', 'source_id' => $visit->id, 'amount' => 500]);
        $client->treatmentCharges()->create(['source_type' => 'visit', 'source_id' => $visit->id, 'amount' => 300]);

        $this->deleteJson("/api/visits/{$visit->id}")->assertOk();

        $this->assertDatabaseCount('treatment_charges', 0);
    }
}
