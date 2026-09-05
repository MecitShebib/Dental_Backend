<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_companys_own_studies_with_series_and_client_name(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-3001',
            'name' => 'Gallery Patient',
            'phone' => '+15550003333',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'uploaded_by' => $doctor->id,
            'modality' => 'CBCT',
            'status' => 'ready',
        ]);
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 10,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/dicom-studies');

        $response->assertOk();
        $response->assertJsonPath('data.0.client_name', 'Gallery Patient');
        $response->assertJsonPath('data.0.series.0.series_uid', '1.2.3');
    }

    public function test_index_excludes_another_companys_studies(): void
    {
        $otherCompany = Company::factory()->create();
        DicomStudy::create([
            'company_id' => $otherCompany->id,
            'uploaded_by' => User::factory()->create(['company_id' => $otherCompany->id])->id,
            'status' => 'ready',
        ]);
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/dicom-studies');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_can_filter_to_unlinked_studies_only(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready', 'client_id' => null]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-3002',
            'name' => 'Linked',
            'phone' => '+15550003334',
            'gender' => 'male',
            'status' => 'new',
        ]);
        DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready', 'client_id' => $client->id]);

        $response = $this->getJson('/api/dicom-studies?unlinked=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
