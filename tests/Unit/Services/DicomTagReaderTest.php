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
    private function buildFixture(array $overrides = [], string $extraElements = '', ?string $pixelData = null): string
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
        $elements .= $extraElements;
        $elements .= $writeElement('7FE00010', 'OB', $pixelData ?? "\0\0\0\0"); // reader stops here

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    public function test_reads_the_tags_this_feature_needs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        file_put_contents($path, $this->buildFixture());

        $tags = (new DicomTagReader)->read($path);
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

        $tags = (new DicomTagReader)->read($path);
        unlink($path);

        $this->assertNull($tags['series_uid']);
        $this->assertNull($tags['modality']);
    }

    public function test_rejects_a_file_without_the_dicm_magic_bytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_dicom_');
        file_put_contents($path, str_repeat('x', 200));

        $this->expectException(\InvalidArgumentException::class);
        (new DicomTagReader)->read($path);
        unlink($path);
    }

    public function test_rejects_a_file_truncated_mid_element_value(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        $bytes = $this->buildFixture();
        // Cut off the last 20 bytes so the final element's declared length
        // promises more value bytes than are actually present.
        file_put_contents($path, substr($bytes, 0, -20));

        $this->expectException(\InvalidArgumentException::class);
        (new DicomTagReader)->read($path);
        unlink($path);
    }

    /**
     * Reproduces a real crash found against an actual vendor CBCT export
     * (an "Enhanced CT Image Storage" multi-frame file): a Nested Functional
     * Groups Sequence with undefined length (0xFFFFFFFF), whose Item/Item
     * Delimitation/Sequence Delimitation pseudo-elements have no VR field at
     * all -- unlike every other DICOM element. Before the fix, the reader
     * misread an Item marker's own bytes as a VR/length pair and corrupted
     * every read after it. This proves the reader correctly walks past the
     * whole sequence and keeps reading the tags that follow it.
     */
    public function test_skips_an_undefined_length_sequence_and_keeps_reading_tags_after_it(): void
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

        // Item/Item-Delimitation/Sequence-Delimitation pseudo-elements: just
        // group(2)+element(2)+length(4), no VR field.
        $writeItemMarker = fn (string $tag, int $length) => pack('vv', hexdec(substr($tag, 0, 4)), hexdec(substr($tag, 4, 4))).pack('V', $length);

        $elements = '';
        $elements .= $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1');
        $elements .= $writeElement('0020000E', 'UI', '1.2.3.SEQTEST');

        // An undefined-length SQ containing one undefined-length Item, which
        // itself contains one ordinary element, closed by an Item
        // Delimitation and then the Sequence Delimitation.
        $elements .= pack('vv', 0x0020, 0x9221).'SQ'."\0\0".pack('V', 0xFFFFFFFF);
        $elements .= $writeItemMarker('FFFEE000', 0xFFFFFFFF);
        $elements .= $writeElement('00080064', 'CS', 'WSD');
        $elements .= $writeItemMarker('FFFEE00D', 0);
        $elements .= $writeItemMarker('FFFEE0DD', 0);

        // Must still be read correctly after the sequence.
        $elements .= $writeElement('00080060', 'CS', 'CT');
        $elements .= $writeElement('7FE00010', 'OB', "\0\0\0\0");

        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        file_put_contents($path, str_repeat("\0", 128).'DICM'.$elements);

        $tags = (new DicomTagReader)->read($path);
        unlink($path);

        $this->assertSame('1.2.3.SEQTEST', $tags['series_uid']);
        $this->assertSame('CT', $tags['modality']);
    }

    /**
     * The geometry DicomFrameExtractor needs to compute a single frame's
     * byte range without re-parsing the whole file: NumberOfFrames, the
     * pixel layout (bits/samples/photometric), and where PixelData's
     * VALUE actually starts (not the tag/VR/length header before it).
     */
    public function test_reads_multiframe_pixel_data_geometry_for_frame_extraction(): void
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

        $extraElements = $writeElement('00280008', 'IS', '3') // NumberOfFrames
            .$writeElement('00280002', 'US', pack('v', 1)) // SamplesPerPixel
            .$writeElement('00280004', 'CS', 'MONOCHROME2')
            .$writeElement('00280100', 'US', pack('v', 16)) // BitsAllocated
            .$writeElement('00280101', 'US', pack('v', 12)) // BitsStored
            .$writeElement('00280102', 'US', pack('v', 11)) // HighBit
            .$writeElement('00280103', 'US', pack('v', 0)); // PixelRepresentation

        $pixelData = str_repeat("\x01\x02", 16 * 16 * 3); // 3 frames of 16x16 16-bit pixels

        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        $fixture = $this->buildFixture(['rows' => 16, 'columns' => 16], $extraElements, $pixelData);
        file_put_contents($path, $fixture);

        $tags = (new DicomTagReader)->read($path);

        $this->assertSame(3, $tags['frame_count']);
        $this->assertSame(1, $tags['samples_per_pixel']);
        $this->assertSame('MONOCHROME2', $tags['photometric_interpretation']);
        $this->assertSame(16, $tags['bits_allocated']);
        $this->assertSame(12, $tags['bits_stored']);
        $this->assertSame(11, $tags['high_bit']);
        $this->assertSame(0, $tags['pixel_representation']);
        $this->assertSame('1.2.840.10008.1.2.1', $tags['transfer_syntax_uid']);
        $this->assertSame(strlen($pixelData), $tags['pixel_data_length']);
        $this->assertNotNull($tags['pixel_data_offset']);

        // The offset must point exactly at the pixel bytes -- re-reading
        // from it must reproduce the original pixel data verbatim.
        $handle = fopen($path, 'rb');
        fseek($handle, $tags['pixel_data_offset']);
        $readBack = fread($handle, $tags['pixel_data_length']);
        fclose($handle);
        unlink($path);

        $this->assertSame($pixelData, $readBack);
    }

    /**
     * Undefined-length PixelData (0xFFFFFFFF) always means encapsulated/
     * compressed fragments in real DICOM -- there's no fixed per-frame byte
     * offset to compute without parsing the Basic Offset Table, so
     * pixel_data_length must come back null even when NumberOfFrames says
     * there's more than one frame. DicomStudyController::store() uses this
     * null to decide is_frame_extractable = false.
     */
    public function test_undefined_length_pixel_data_reports_null_length(): void
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
        $elements .= $writeElement('00020010', 'UI', '1.2.840.10008.1.2.4.90'); // JPEG2000 (compressed)
        $elements .= $writeElement('0020000E', 'UI', '1.2.3.COMPRESSED');
        $elements .= $writeElement('00280008', 'IS', '10');
        // Encapsulated PixelData: OB, undefined length, followed by a Basic
        // Offset Table item and fragment(s) -- this reader stops at the tag
        // itself and never needs to walk the fragments.
        $elements .= pack('vv', 0x7FE0, 0x0010).'OB'."\0\0".pack('V', 0xFFFFFFFF);
        $elements .= pack('vv', 0xFFFE, 0xE000).pack('V', 0); // empty Basic Offset Table item

        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        file_put_contents($path, str_repeat("\0", 128).'DICM'.$elements);

        $tags = (new DicomTagReader)->read($path);
        unlink($path);

        $this->assertSame(10, $tags['frame_count']);
        $this->assertNull($tags['pixel_data_length']);
    }
}
