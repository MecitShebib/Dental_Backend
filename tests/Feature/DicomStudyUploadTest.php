<?php

namespace Tests\Feature;

use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class DicomStudyUploadTest extends TestCase
{
    use RefreshDatabase;

    private function buildDicomBytes(string $seriesUid, string $modality = 'CBCT'): string
    {
        $pad = fn (string $value) => strlen($value) % 2 === 0 ? $value : $value."\0";
        $writeElement = function (string $tag, string $vr, string $value) use ($pad) {
            $group = hexdec(substr($tag, 0, 4));
            $element = hexdec(substr($tag, 4, 4));
            $value = $pad($value);
            $bytes = pack('vv', $group, $element).$vr;
            $bytes .= in_array($vr, ['OB', 'OW'], true)
                ? "\0\0".pack('V', strlen($value))
                : pack('v', strlen($value));

            return $bytes.$value;
        };

        $elements = '';
        $elements .= $writeElement('0020000E', 'UI', $seriesUid);
        $elements .= $writeElement('00080060', 'CS', $modality);
        $elements .= $writeElement('00080020', 'DA', '20260901');
        $elements .= $writeElement('00081030', 'LO', 'Full Arch Scan');
        $elements .= $writeElement('00280010', 'US', pack('v', 512));
        $elements .= $writeElement('00280011', 'US', pack('v', 512));
        $elements .= $writeElement('00280030', 'DS', '0.3\\0.3');
        $elements .= $writeElement('00180050', 'DS', '0.5');
        $elements .= $writeElement('00200037', 'DS', '1\\0\\0\\0\\1\\0');
        $elements .= $writeElement('7FE00010', 'OB', "\0\0\0\0");

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    private function buildMultiFrameDicomBytes(string $seriesUid, int $frameCount, int $rows = 16, int $columns = 16): string
    {
        $pad = fn (string $value) => strlen($value) % 2 === 0 ? $value : $value."\0";
        $writeElement = function (string $tag, string $vr, string $value) use ($pad) {
            $group = hexdec(substr($tag, 0, 4));
            $element = hexdec(substr($tag, 4, 4));
            $value = $pad($value);
            $bytes = pack('vv', $group, $element).$vr;
            $bytes .= in_array($vr, ['OB', 'OW'], true)
                ? "\0\0".pack('V', strlen($value))
                : pack('v', strlen($value));

            return $bytes.$value;
        };

        $elements = '';
        $elements .= $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1');
        $elements .= $writeElement('0020000E', 'UI', $seriesUid);
        $elements .= $writeElement('00080060', 'CS', 'CT');
        $elements .= $writeElement('00280008', 'IS', (string) $frameCount);
        $elements .= $writeElement('00280002', 'US', pack('v', 1));
        $elements .= $writeElement('00280004', 'CS', 'MONOCHROME2');
        $elements .= $writeElement('00280010', 'US', pack('v', $rows));
        $elements .= $writeElement('00280011', 'US', pack('v', $columns));
        $elements .= $writeElement('00280100', 'US', pack('v', 16));
        $elements .= $writeElement('00280101', 'US', pack('v', 16));
        $elements .= $writeElement('00280102', 'US', pack('v', 15));
        $elements .= $writeElement('00280103', 'US', pack('v', 0));
        $elements .= $writeElement('7FE00010', 'OW', str_repeat("\0\0", $rows * $columns * $frameCount));

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    private function activeDoctor(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        \App\Models\Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);

        return $doctor;
    }

    public function test_uploading_loose_dcm_files_from_one_series_creates_one_study_and_series(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $slice1 = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-A'));
        $slice2 = UploadedFile::fake()->createWithContent('slice2.dcm', $this->buildDicomBytes('1.2.3.SERIES-A'));

        $response = $this->post('/api/dicom-studies', ['files' => [$slice1, $slice2]]);

        $response->assertCreated();
        $this->assertDatabaseCount('dicom_studies', 1);
        $this->assertDatabaseHas('dicom_studies', ['status' => 'ready', 'modality' => 'CBCT', 'slice_count' => 2]);
        $this->assertDatabaseHas('dicom_series', ['series_uid' => '1.2.3.SERIES-A', 'slice_count' => 2]);
    }

    public function test_uploading_a_zip_extracts_and_groups_by_series(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $zipPath = tempnam(sys_get_temp_dir(), 'dicom_zip_').'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-B'));
        $zip->addFromString('slice2.dcm', $this->buildDicomBytes('1.2.3.SERIES-B'));
        $zip->addFromString('readme.txt', 'not a dicom file'); // must be ignored, not crash the upload
        $zip->close();

        $response = $this->post('/api/dicom-studies', [
            'archive' => new UploadedFile($zipPath, 'study.zip', 'application/zip', null, true),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dicom_series', ['series_uid' => '1.2.3.SERIES-B', 'slice_count' => 2]);
        unlink($zipPath);
    }

    public function test_a_study_can_be_uploaded_already_linked_to_a_client(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = \App\Models\Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-2001',
            'name' => 'Linked Patient',
            'phone' => '+15550002222',
            'gender' => 'female',
            'status' => 'new',
        ]);

        $slice = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-C'));
        $response = $this->post('/api/dicom-studies', ['files' => [$slice], 'client_id' => $client->id]);

        $response->assertCreated();
        $this->assertSame($client->id, DicomStudy::first()->client_id);
    }

    public function test_uploading_a_corrupted_zip_returns_validation_error(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $fakeZip = UploadedFile::fake()->createWithContent('corrupted.zip', 'this-is-not-a-real-zip-file');

        $response = $this->postJson('/api/dicom-studies', ['archive' => $fakeZip]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archive');
    }

    public function test_uploading_a_single_multiframe_file_marks_the_series_frame_extractable(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $file = UploadedFile::fake()->createWithContent(
            'scan.dcm',
            $this->buildMultiFrameDicomBytes('1.2.3.MULTIFRAME', frameCount: 40)
        );

        $response = $this->post('/api/dicom-studies', ['files' => [$file]]);

        $response->assertCreated();
        // slice_count stays 1 (one uploaded file) -- frame_count is the new,
        // separate field the viewer actually cares about for a multi-frame
        // upload like a real CBCT export.
        $this->assertDatabaseHas('dicom_series', [
            'series_uid' => '1.2.3.MULTIFRAME',
            'slice_count' => 1,
            'frame_count' => 40,
            'is_frame_extractable' => true,
        ]);
    }

    public function test_uploading_two_loose_files_in_one_series_is_not_frame_extractable(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        // Two genuinely separate single-frame files in the same series --
        // the per-file whole-file streaming path, not per-frame extraction,
        // even though each file could theoretically claim NumberOfFrames=1.
        $slice1 = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-E'));
        $slice2 = UploadedFile::fake()->createWithContent('slice2.dcm', $this->buildDicomBytes('1.2.3.SERIES-E'));

        $response = $this->post('/api/dicom-studies', ['files' => [$slice1, $slice2]]);

        $response->assertCreated();
        $this->assertDatabaseHas('dicom_series', [
            'series_uid' => '1.2.3.SERIES-E',
            'slice_count' => 2,
            'is_frame_extractable' => false,
        ]);
    }

    public function test_uploading_with_another_companys_client_id_is_rejected(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        // No ClientFactory exists in this codebase, so build the "other
        // company" fixture explicitly -- same pattern XrayImageTest uses
        // (Company::factory()->create() + Client::create()).
        $otherCompany = \App\Models\Company::factory()->create();
        $otherCompanyClient = \App\Models\Client::create([
            'company_id' => $otherCompany->id,
            'client_code' => 'CL-OTHER-1',
            'name' => 'Other Company Patient',
            'phone' => '+15550003333',
            'gender' => 'female',
            'status' => 'new',
        ]);

        $slice = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-D'));
        $response = $this->postJson('/api/dicom-studies', ['files' => [$slice], 'client_id' => $otherCompanyClient->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('client_id');
    }

    public function test_a_malicious_series_uid_cannot_escape_the_studys_storage_directory(): void
    {
        // Security regression test: DicomTagReader applies no character/
        // format validation to the Series Instance UID tag -- it's read
        // straight out of attacker-controlled file bytes. This used to be
        // used verbatim as the storage directory name
        // ("dicom-studies/{uuid}/{series_uid}"), and Flysystem's path
        // normalizer only rejects ".." segments once they'd pop past an
        // *empty* accumulator -- with two real segments already ahead of it,
        // "../../xray-images" normalized straight through to "xray-images",
        // the shared cross-tenant X-ray image store on this same disk.
        // destroy() later calls deleteDirectory() on that stored path
        // unmodified, so an attacker could upload a study with this tag,
        // then delete it to wipe every company's X-ray images platform-wide.
        Sanctum::actingAs($this->activeDoctor());

        $maliciousSeriesUid = '../../xray-images';
        $slice = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes($maliciousSeriesUid));

        $response = $this->postJson('/api/dicom-studies', ['files' => [$slice]]);
        $response->assertCreated();

        $series = \App\Models\DicomSeries::query()->where('series_uid', $maliciousSeriesUid)->firstOrFail();

        // The raw tag value is fine to keep in the series_uid *column* (a
        // plain DB value, never touched by any Storage:: call) -- the actual
        // requirement is that it never appears inside the storage *path*.
        $this->assertStringNotContainsString('..', $series->storage_path);
        $this->assertStringNotContainsString($maliciousSeriesUid, $series->storage_path);
        $this->assertStringStartsWith("dicom-studies/{$series->study->uuid}/", $series->storage_path);

        // And the file itself really did land inside that safe directory,
        // not anywhere the traversal was aiming for.
        $this->assertTrue(Storage::disk('local')->exists("{$series->storage_path}/0.dcm"));
        $this->assertFalse(Storage::disk('local')->exists('xray-images/0.dcm'));
    }
}
