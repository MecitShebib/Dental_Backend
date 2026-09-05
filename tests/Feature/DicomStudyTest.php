<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DicomSeries;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_study_belongs_to_a_company_and_optionally_a_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-1001',
            'name' => 'Study Patient',
            'phone' => '+15550001111',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($doctor);

        $study = DicomStudy::create([
            'client_id' => $client->id,
            'uploaded_by' => $doctor->id,
            'modality' => 'CBCT',
            'study_date' => '2026-09-01',
            'description' => 'Full arch scan',
            'slice_count' => 200,
            'status' => 'ready',
        ]);

        $this->assertSame($doctor->company_id, $study->company_id);
        $this->assertTrue($doctor->company->dicomStudies->contains('id', $study->id));
        $this->assertTrue($client->dicomStudies->contains('id', $study->id));
    }

    public function test_a_study_has_many_series(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $study = DicomStudy::create([
            'uploaded_by' => $doctor->id,
            'modality' => 'CT',
            'status' => 'ready',
        ]);

        $series = DicomSeries::create([
            'dicom_study_id' => $study->id,
            'series_uid' => '1.2.3.4.5',
            'rows' => 512,
            'columns' => 512,
            'slice_count' => 150,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3.4.5",
        ]);

        $this->assertTrue($study->series->contains('id', $series->id));
        $this->assertSame($study->id, $series->study->id);
    }
}
