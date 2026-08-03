<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddAiTreatmentPlanChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(string $code = 'CL-9101'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Rama',
            'phone' => '+963900009101',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_a_doctor_can_record_charge_items(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [
                ['description' => 'Consultation fee', 'amount' => 150.5],
                ['description' => 'Discount', 'amount' => -20],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'manual',
            'amount' => 150.5,
            'description' => 'Consultation fee',
            'created_by' => $doctor->id,
        ]);
        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'source_type' => 'manual',
            'amount' => -20,
            'description' => 'Discount',
            'created_by' => $doctor->id,
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.financial_summary.total_services_amount', 130.5);
    }

    public function test_appending_charges_does_not_wipe_out_earlier_manual_charges(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9105');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['description' => 'First visit fee', 'amount' => 100]],
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['description' => 'Second visit fee', 'amount' => 75]],
        ])->assertCreated();

        $this->assertDatabaseCount('treatment_charges', 2);
        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.financial_summary.total_services_amount', 175);
    }

    public function test_description_is_optional(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9102');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['amount' => 75]],
        ])->assertCreated();

        $this->assertDatabaseHas('treatment_charges', [
            'client_id' => $client->id,
            'amount' => 75,
            'description' => null,
        ]);
    }

    public function test_a_non_doctor_cannot_record_a_charge(): void
    {
        $nonDoctor = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($nonDoctor);
        $client = $this->makeClient('CL-9103');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['amount' => 100]],
        ])->assertStatus(422)->assertJsonValidationErrors('doctor');

        $this->assertDatabaseCount('treatment_charges', 0);
    }

    public function test_charge_items_are_required(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9104');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [])
            ->assertStatus(422)->assertJsonValidationErrors('charge_items');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'charge_items' => [['description' => 'Missing amount']],
        ])->assertStatus(422)->assertJsonValidationErrors('charge_items.0.amount');

        $this->assertDatabaseCount('treatment_charges', 0);
    }
}
