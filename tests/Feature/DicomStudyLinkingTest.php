<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_study_can_be_linked_to_a_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-4001',
            'name' => 'Linkable',
            'phone' => '+15550004444',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'uploaded_by' => $doctor->id,
            'status' => 'ready',
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/dicom-studies/{$study->id}", ['client_id' => $client->id]);

        $response->assertOk();
        $this->assertSame($client->id, $study->fresh()->client_id);
    }

    public function test_a_study_cannot_be_linked_to_another_companys_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $otherClient = Client::create([
            'company_id' => \App\Models\Company::factory()->create()->id,
            'client_code' => 'CL-4002',
            'name' => 'Other Company Client',
            'phone' => '+15550005555',
            'gender' => 'female',
            'status' => 'new',
        ]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'uploaded_by' => $doctor->id,
            'status' => 'ready',
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/dicom-studies/{$study->id}", ['client_id' => $otherClient->id]);

        $response->assertStatus(422);
    }

    public function test_deleting_a_study_removes_its_stored_files(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'uploaded_by' => $doctor->id,
            'status' => 'ready',
        ]);
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('local')->put("dicom-studies/{$study->uuid}/1.2.3/0.dcm", 'fake-bytes');
        Sanctum::actingAs($doctor);

        $response = $this->deleteJson("/api/dicom-studies/{$study->id}");

        $response->assertOk();
        Storage::disk('local')->assertMissing("dicom-studies/{$study->uuid}/1.2.3/0.dcm");
    }

    public function test_a_study_can_be_unlinked_from_a_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-4003',
            'name' => 'Initially Linked',
            'phone' => '+15550006666',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'client_id' => $client->id,
            'uploaded_by' => $doctor->id,
            'status' => 'ready',
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/dicom-studies/{$study->id}", ['client_id' => null]);

        $response->assertOk();
        $this->assertNull($study->fresh()->client_id);
    }

    public function test_deleting_a_study_also_removes_its_series_rows(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create([
            'company_id' => $doctor->company_id,
            'uploaded_by' => $doctor->id,
            'status' => 'ready',
        ]);
        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->deleteJson("/api/dicom-studies/{$study->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('dicom_series', ['id' => $series->id]);
        $this->assertDatabaseMissing('dicom_studies', ['id' => $study->id]);
    }
}
