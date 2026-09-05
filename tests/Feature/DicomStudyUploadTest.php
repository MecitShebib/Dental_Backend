<?php

namespace Tests\Feature;

use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
