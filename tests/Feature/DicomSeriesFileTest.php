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

    public function test_a_frame_extractable_series_mints_one_signed_url_per_frame(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);
        // slice_count deliberately stays 1 (one uploaded file) -- frame_count
        // is what image_urls should actually iterate when is_frame_extractable.
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'frame_count' => 40,
            'is_frame_extractable' => true,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/dicom-studies/{$study->id}");

        $response->assertOk();
        $urls = $response->json('data.series.0.image_urls');
        $this->assertCount(40, $urls);
        $this->assertStringContainsString('/api/dicom-series/', $urls[0]);
        $this->assertStringContainsString('/frames/', $urls[0]);
        $this->assertStringContainsString('signature=', $urls[0]);
    }

    public function test_a_signed_frame_url_streams_a_valid_single_frame_dicom_file(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);

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
        $rows = 4;
        $columns = 4;
        $frameCount = 2;
        $elements = $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1')
            .$writeElement('0020000E', 'UI', '1.2.3')
            .$writeElement('00280008', 'IS', (string) $frameCount)
            .$writeElement('00280002', 'US', pack('v', 1))
            .$writeElement('00280004', 'CS', 'MONOCHROME2')
            .$writeElement('00280010', 'US', pack('v', $rows))
            .$writeElement('00280011', 'US', pack('v', $columns))
            .$writeElement('00280100', 'US', pack('v', 16))
            .$writeElement('00280101', 'US', pack('v', 16))
            .$writeElement('00280102', 'US', pack('v', 15))
            .$writeElement('00280103', 'US', pack('v', 0))
            .$writeElement('7FE00010', 'OW', str_repeat(pack('v', 0), $rows * $columns).str_repeat(pack('v', 1), $rows * $columns));
        $bytes = str_repeat("\0", 128).'DICM'.$elements;

        $tags = (new \App\Services\DicomTagReader)->read(
            tap(tempnam(sys_get_temp_dir(), 'dicom_'), fn ($p) => file_put_contents($p, $bytes))
        );

        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'frame_count' => $frameCount,
            'rows' => $rows,
            'columns' => $columns,
            'bits_allocated' => 16,
            'bits_stored' => 16,
            'high_bit' => 15,
            'pixel_representation' => 0,
            'samples_per_pixel' => 1,
            'photometric_interpretation' => 'MONOCHROME2',
            'transfer_syntax_uid' => '1.2.840.10008.1.2.1',
            'pixel_data_offset' => $tags['pixel_data_offset'],
            'is_frame_extractable' => true,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('local')->put("{$series->storage_path}/0.dcm", $bytes);

        $url = URL::temporarySignedRoute('dicom-series.frame', now()->addMinutes(60), [
            'dicomSeries' => $series->id,
            'frame' => 1,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/dicom');

        $reRead = (new \App\Services\DicomTagReader)->read(
            tap(tempnam(sys_get_temp_dir(), 'dicom_out_'), fn ($p) => file_put_contents($p, $response->getContent()))
        );
        $this->assertSame(4, $reRead['rows']);
        $this->assertSame(4, $reRead['columns']);
        $this->assertSame(1, $reRead['frame_count']);
    }

    public function test_requesting_a_frame_out_of_range_returns_not_found(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);
        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'frame_count' => 5,
            'is_frame_extractable' => true,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);

        $url = URL::temporarySignedRoute('dicom-series.frame', now()->addMinutes(60), [
            'dicomSeries' => $series->id,
            'frame' => 5,
        ]);

        $response = $this->get($url);

        $response->assertNotFound();
    }

    /**
     * A real CBCT scan means the viewer fetching a frame at a time for
     * several hundred frames within a few seconds -- reproduces the "HTTP
     * 429" a user hit opening a real 534-frame scan, caused by the general
     * 'api' rate limiter's 120/min (applied to every route in this file by
     * default) being far too tight for that. dicom-series.frame is split
     * into its own route group in routes/api.php specifically to swap that
     * for 'dicom-frame-stream' (3000/min) instead -- this fires well past
     * the old 120 limit and asserts none of them 429.
     */
    public function test_many_rapid_frame_requests_are_not_rate_limited(): void
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['company_id' => $doctor->company_id, 'uploaded_by' => $doctor->id, 'status' => 'ready']);

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
        $elements = $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1')
            .$writeElement('0020000E', 'UI', '1.2.3')
            .$writeElement('00280008', 'IS', '1')
            .$writeElement('00280002', 'US', pack('v', 1))
            .$writeElement('00280004', 'CS', 'MONOCHROME2')
            .$writeElement('00280010', 'US', pack('v', 2))
            .$writeElement('00280011', 'US', pack('v', 2))
            .$writeElement('00280100', 'US', pack('v', 16))
            .$writeElement('00280101', 'US', pack('v', 16))
            .$writeElement('00280102', 'US', pack('v', 15))
            .$writeElement('00280103', 'US', pack('v', 0))
            .$writeElement('7FE00010', 'OW', str_repeat(pack('v', 0), 4));
        $bytes = str_repeat("\0", 128).'DICM'.$elements;

        $tags = (new \App\Services\DicomTagReader)->read(
            tap(tempnam(sys_get_temp_dir(), 'dicom_'), fn ($p) => file_put_contents($p, $bytes))
        );

        $series = $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'frame_count' => 1,
            'rows' => 2,
            'columns' => 2,
            'bits_allocated' => 16,
            'bits_stored' => 16,
            'high_bit' => 15,
            'pixel_representation' => 0,
            'samples_per_pixel' => 1,
            'photometric_interpretation' => 'MONOCHROME2',
            'transfer_syntax_uid' => '1.2.840.10008.1.2.1',
            'pixel_data_offset' => $tags['pixel_data_offset'],
            'is_frame_extractable' => true,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('local')->put("{$series->storage_path}/0.dcm", $bytes);

        $url = URL::temporarySignedRoute('dicom-series.frame', now()->addMinutes(60), [
            'dicomSeries' => $series->id,
            'frame' => 0,
        ]);

        for ($i = 0; $i < 130; $i++) {
            $response = $this->get($url);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} was rate-limited (429).");
        }
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
