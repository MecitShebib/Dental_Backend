<?php

namespace Tests\Unit\Services;

use App\Services\DicomTagReader;
use PHPUnit\Framework\TestCase;

class DicomTagReaderTest extends TestCase
{
    /**
     * Builds a minimal, real Explicit-VR-Little-Endian DICOM byte stream
     * containing only the tags this reader cares about, terminated by a
     * PixelData element (so the reader has something to stop at, matching
     * a real file where PixelData is the last/largest element it will ever
     * need to look past).
     */
    private function buildFixture(array $overrides = []): string
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
        $elements .= $writeElement('0020000E', 'UI', $overrides['series_uid'] ?? '1.2.3.4.5.6');
        $elements .= $writeElement('00080060', 'CS', $overrides['modality'] ?? 'CT');
        $elements .= $writeElement('00080020', 'DA', $overrides['study_date'] ?? '20260901');
        $elements .= $writeElement('00081030', 'LO', $overrides['study_description'] ?? 'Full Arch Scan');
        $elements .= $writeElement('00280010', 'US', pack('v', $overrides['rows'] ?? 512));
        $elements .= $writeElement('00280011', 'US', pack('v', $overrides['columns'] ?? 512));
        $elements .= $writeElement('00280030', 'DS', $overrides['pixel_spacing'] ?? '0.3\\0.3');
        $elements .= $writeElement('00180050', 'DS', $overrides['slice_thickness'] ?? '0.5');
        $elements .= $writeElement('00200037', 'DS', $overrides['orientation'] ?? '1\\0\\0\\0\\1\\0');
        $elements .= $writeElement('7FE00010', 'OB', "\0\0\0\0"); // dummy pixel data, reader stops here

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    public function test_reads_the_tags_this_feature_needs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        file_put_contents($path, $this->buildFixture());

        $tags = (new DicomTagReader())->read($path);
        unlink($path);

        $this->assertSame('1.2.3.4.5.6', $tags['series_uid']);
        $this->assertSame('CT', $tags['modality']);
        $this->assertSame('20260901', $tags['study_date']);
        $this->assertSame('Full Arch Scan', $tags['study_description']);
        $this->assertSame(512, $tags['rows']);
        $this->assertSame(512, $tags['columns']);
        $this->assertSame(0.3, $tags['pixel_spacing_x']);
        $this->assertSame(0.3, $tags['pixel_spacing_y']);
        $this->assertSame(0.5, $tags['slice_thickness']);
        $this->assertSame('1\\0\\0\\0\\1\\0', $tags['orientation']);
    }

    public function test_missing_optional_tags_come_back_null_instead_of_erroring(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        // A file with only the required magic bytes and pixel data -- no
        // metadata tags at all, to prove partial/malformed-but-parseable
        // files don't crash the reader.
        file_put_contents($path, str_repeat("\0", 128).'DICM');

        $tags = (new DicomTagReader())->read($path);
        unlink($path);

        $this->assertNull($tags['series_uid']);
        $this->assertNull($tags['modality']);
    }

    public function test_rejects_a_file_without_the_dicm_magic_bytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_dicom_');
        file_put_contents($path, str_repeat('x', 200));

        $this->expectException(\InvalidArgumentException::class);
        (new DicomTagReader())->read($path);
        unlink($path);
    }
}
