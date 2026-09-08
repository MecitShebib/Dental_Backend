<?php

namespace Tests\Unit\Services;

use App\Models\DicomSeries;
use App\Services\DicomFrameExtractor;
use App\Services\DicomTagReader;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DicomFrameExtractorTest extends TestCase
{
    /**
     * Builds a real, valid multi-frame Explicit-VR-LE DICOM file (several
     * distinct frames, each filled with its own frame index so a wrong byte
     * offset is immediately obvious) and stores it exactly where
     * DicomFrameExtractor expects a single-file series' one file to live.
     */
    private function makeMultiFrameSeries(string $storagePath, int $rows, int $columns, int $frameCount): DicomSeries
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

        $frameByteLength = $rows * $columns * 2; // 16-bit, 1 sample per pixel
        $pixelData = '';
        for ($frame = 0; $frame < $frameCount; $frame++) {
            // Every pixel in a frame equals that frame's own index, so
            // reading back frame N's bytes and checking they're all == N
            // proves the offset math landed on the right frame.
            $pixelData .= str_repeat(pack('v', $frame), $rows * $columns);
        }
        $this->assertSame($frameByteLength * $frameCount, strlen($pixelData));

        $elements = '';
        $elements .= $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1');
        $elements .= $writeElement('0020000E', 'UI', '1.2.3.FRAMETEST');
        $elements .= $writeElement('00280008', 'IS', (string) $frameCount);
        $elements .= $writeElement('00280002', 'US', pack('v', 1));
        $elements .= $writeElement('00280004', 'CS', 'MONOCHROME2');
        $elements .= $writeElement('00280010', 'US', pack('v', $rows));
        $elements .= $writeElement('00280011', 'US', pack('v', $columns));
        $elements .= $writeElement('00280100', 'US', pack('v', 16));
        $elements .= $writeElement('00280101', 'US', pack('v', 16));
        $elements .= $writeElement('00280102', 'US', pack('v', 15));
        $elements .= $writeElement('00280103', 'US', pack('v', 0));
        $elements .= $writeElement('7FE00010', 'OW', $pixelData);

        $bytes = str_repeat("\0", 128).'DICM'.$elements;

        Storage::disk('local')->put("{$storagePath}/0.dcm", $bytes);

        $tags = (new DicomTagReader)->read(Storage::disk('local')->path("{$storagePath}/0.dcm"));

        return new DicomSeries([
            'uuid' => 'frame-extractor-test-series',
            'series_uid' => $tags['series_uid'],
            'rows' => $tags['rows'],
            'columns' => $tags['columns'],
            'slice_count' => 1,
            'frame_count' => $tags['frame_count'],
            'bits_allocated' => $tags['bits_allocated'],
            'bits_stored' => $tags['bits_stored'],
            'high_bit' => $tags['high_bit'],
            'pixel_representation' => $tags['pixel_representation'],
            'samples_per_pixel' => $tags['samples_per_pixel'],
            'photometric_interpretation' => $tags['photometric_interpretation'],
            'sop_class_uid' => $tags['sop_class_uid'],
            'transfer_syntax_uid' => $tags['transfer_syntax_uid'],
            'pixel_data_offset' => $tags['pixel_data_offset'],
            'is_frame_extractable' => true,
            'slice_thickness' => 0.5,
            'storage_path' => $storagePath,
        ]);
    }

    public function test_extracts_the_correct_bytes_for_a_given_frame(): void
    {
        Storage::fake('local');
        $series = $this->makeMultiFrameSeries('dicom-test/frame-extractor', 8, 8, 5);

        $extractor = new DicomFrameExtractor;

        foreach ([0, 2, 4] as $frameIndex) {
            $bytes = $extractor->extractFrame($series, $frameIndex);
            $reRead = (new DicomTagReader)->read($this->writeTempFile($bytes));

            $this->assertSame(8, $reRead['rows']);
            $this->assertSame(8, $reRead['columns']);
            $this->assertSame(1, $reRead['frame_count']); // synthetic file is single-frame

            $handle = fopen($this->writeTempFile($bytes), 'rb');
            fseek($handle, $reRead['pixel_data_offset']);
            $pixelBytes = fread($handle, $reRead['pixel_data_length']);
            fclose($handle);

            // Every pixel in the extracted frame must equal this frame's
            // own index -- proves the byte offset landed on frame N, not
            // some neighboring frame.
            $expected = str_repeat(pack('v', $frameIndex), 8 * 8);
            $this->assertSame($expected, $pixelBytes);
        }
    }

    public function test_throws_for_a_frame_index_out_of_range(): void
    {
        Storage::fake('local');
        $series = $this->makeMultiFrameSeries('dicom-test/frame-extractor-range', 4, 4, 3);

        $extractor = new DicomFrameExtractor;

        $this->expectException(\OutOfRangeException::class);
        $extractor->extractFrame($series, 3);
    }

    private function writeTempFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_frame_test_');
        file_put_contents($path, $bytes);

        return $path;
    }
}
