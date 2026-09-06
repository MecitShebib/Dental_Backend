<?php

namespace Tests\Feature;

use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomSeriesFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_dicom_study_resource_mints_one_signed_url_per_slice(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 3,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/dicom-studies/{$study->id}");

        $response->assertOk();
        $urls = $response->json('data.series.0.image_urls');
        $this->assertCount(3, $urls);
        $this->assertStringContainsString('/api/dicom-series/', $urls[0]);
        $this->assertStringContainsString('signature=', $urls[0]);
    }

    public function test_a_signed_url_streams_the_slice_file(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);
        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('local')->put("{$series->storage_path}/0.dcm", 'fake-dicom-bytes');

        $url = URL::temporarySignedRoute('dicom-series.file', now()->addMinutes(60), [
            'dicomSeries' => $series->id,
            'index' => 0,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertStreamedContent('fake-dicom-bytes');
    }

    public function test_an_unsigned_url_is_rejected(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);
        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('local')->put("{$series->storage_path}/0.dcm", 'fake-dicom-bytes');

        $response = $this->get("/api/dicom-series/{$series->id}/files/0");

        $response->assertForbidden();
    }
}
