<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabCaseAutoCreationTest extends TestCase
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

    protected function odontogramSummary(array $teeth): string
    {
        return json_encode([
            '__visit_odontogram__' => true,
            'companyVersion' => 2,
            'odontogramV2Status' => [
                'version' => '1.3',
                'globals' => ['wisdomVisible' => true, 'showBase' => true, 'occlusalVisible' => true, 'showHealthyPulp' => true, 'edentulous' => false],
                'teeth' => $teeth,
            ],
            'odontogramV2PricingOverrides' => [],
        ]);
    }

    public function test_saving_a_visit_with_a_crown_auto_creates_a_lab_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $summary = $this->odontogramSummary([
            '11' => ['restorationType' => 'crown', 'restorationMaterial' => 'zirconia'],
        ]);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-05',
            'summary' => $summary,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.work_type', 'crown')
            ->assertJsonPath('data.0.teeth', ['11'])
            ->assertJsonPath('data.0.doctor_name', $doctor->name);
    }

    public function test_teeth_with_the_same_work_type_from_one_save_are_grouped_into_one_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $summary = $this->odontogramSummary([
            '21' => ['restorationType' => 'bridge', 'restorationMaterial' => 'zirconia'],
            '22' => ['restorationType' => 'bridge', 'restorationMaterial' => 'zirconia'],
            '23' => ['restorationType' => 'bridge', 'restorationMaterial' => 'zirconia'],
        ]);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-05',
            'summary' => $summary,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.work_type', 'bridge')
            ->assertJsonPath('data.0.teeth', ['21', '22', '23']);
    }

    public function test_non_lab_restoration_types_do_not_create_a_lab_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $summary = $this->odontogramSummary([
            '14' => ['restorationType' => 'inlay', 'restorationMaterial' => 'composite'],
            '15' => ['fillingMaterial' => 'composite', 'fillingSurfaces' => ['occlusal']],
        ]);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-05',
            'summary' => $summary,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/lab-cases")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_resaving_the_same_visit_does_not_create_a_duplicate_lab_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $summary = $this->odontogramSummary([
            '11' => ['restorationType' => 'crown', 'restorationMaterial' => 'zirconia'],
        ]);

        $visitId = $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-05',
            'summary' => $summary,
        ])->assertCreated()->json('data.id');

        // Re-saving the same visit (e.g. editing notes, not the odontogram)
        // must not spawn a second lab case for the same tooth+work type.
        $this->putJson("/api/visits/{$visitId}", [
            'summary' => $summary,
            'notes' => 'Follow-up note',
        ])->assertOk();

        $this->getJson("/api/clients/{$client->id}/lab-cases")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_new_crown_on_a_different_tooth_in_a_later_visit_creates_a_second_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-05',
            'summary' => $this->odontogramSummary(['11' => ['restorationType' => 'crown', 'restorationMaterial' => 'zirconia']]),
        ])->assertCreated();

        $this->postJson("/api/clients/{$client->id}/visits", [
            'doctor_id' => $doctor->id,
            'visit_date' => '2026-08-12',
            'summary' => $this->odontogramSummary(['26' => ['restorationType' => 'crown', 'restorationMaterial' => 'zirconia']]),
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/lab-cases")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_saving_the_treatment_record_current_condition_also_auto_creates_a_lab_case(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = $this->makeClient($company);
        Sanctum::actingAs($doctor);

        $notes = $this->odontogramSummary([
            '36' => ['restorationType' => 'veneer', 'restorationMaterial' => 'porcelain'],
        ]);

        $this->putJson("/api/clients/{$client->id}/treatment-record", [
            'notes' => $notes,
        ])->assertOk();

        $this->getJson("/api/clients/{$client->id}/lab-cases")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.work_type', 'veneer')
            ->assertJsonPath('data.0.teeth', ['36']);
    }
}
