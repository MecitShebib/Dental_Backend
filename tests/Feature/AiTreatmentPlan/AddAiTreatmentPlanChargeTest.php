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

    public function test_a_doctor_can_record_a_charge(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 150.5,
            'description' => 'Root canal, 2 sessions',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('ai_treatment_plan_charges', [
            'client_id' => $client->id,
            'amount' => 150.5,
            'description' => 'Root canal, 2 sessions',
            'created_by' => $doctor->id,
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.financial_summary.total_services_amount', 150.5);
    }

    public function test_description_is_optional(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9102');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 75,
        ])->assertCreated();

        $this->assertDatabaseHas('ai_treatment_plan_charges', [
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
            'amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('doctor');

        $this->assertDatabaseCount('ai_treatment_plan_charges', 0);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9104');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => -10,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('ai_treatment_plan_charges', 0);
    }
}
