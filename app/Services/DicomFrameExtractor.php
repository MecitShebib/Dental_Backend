<?php

namespace App\Services;

use App\Models\DicomSeries;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a small, standalone, valid single-frame DICOM file for exactly one
 * frame of a multi-frame series' stored file -- reusing the geometry
 * DicomTagReader already extracted and persisted at upload time
 * (DicomSeries::pixel_data_offset/frame_count/etc.) instead of re-parsing
 * the whole file per request.
 *
 * Only ever called when DicomSeries::is_frame_extractable is true (native,
 * fixed-length PixelData -- see the migration/DicomStudyController for why
 * encapsulated/compressed pixel data can't use this).
 */
class DicomFrameExtractor
{
    public function extractFrame(DicomSeries $series, int $frameIndex): string
    {
        if ($frameIndex < 0 || $frameIndex >= $series->frame_count) {
            throw new \OutOfRangeException("Frame {$frameIndex} is out of range (series has {$series->frame_count} frames).");
        }

        $bytesPerSample = (int) ceil($series->bits_allocated / 8);
        $frameByteLength = $series->rows * $series->columns * $series->samples_per_pixel * $bytesPerSample;
        $frameOffset = $series->pixel_data_offset + ($frameIndex * $frameByteLength);

        // Only ever called for a series where is_frame_extractable was set
        // -- that's only true for a single-file, single-frame-source series
        // (see DicomStudyController::store()), whose one uploaded file is
        // always saved as index 0.
        $filePath = Storage::disk('local')->path("{$series->storage_path}/0.dcm");
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open series file: {$filePath}");
        }

        try {
            fseek($handle, $frameOffset);
            $pixelData = fread($handle, $frameByteLength);
            if ($pixelData === false || strlen($pixelData) !== $frameByteLength) {
                throw new \RuntimeException("Truncated read for frame {$frameIndex} of series {$series->id}.");
            }
        } finally {
            fclose($handle);
        }

        return $this->buildSingleFrameDicom($series, $frameIndex, $pixelData);
    }

    private function buildSingleFrameDicom(DicomSeries $series, int $frameIndex, string $pixelData): string
    {
        $pad = fn (string $v): string => strlen($v) % 2 === 0 ? $v : $v."\0";

        $writeElement = function (string $tag, string $vr, string $value) use ($pad): string {
            $group = hexdec(substr($tag, 0, 4));
            $element = hexdec(substr($tag, 4, 4));
            $value = $pad($value);
            $bytes = pack('vv', $group, $element).$vr;
            $bytes .= in_array($vr, ['OB', 'OW'], true)
                ? "\0\0".pack('V', strlen($value))
                : pack('v', strlen($value));

            return $bytes.$value;
        };

        // Frame-unique SOP Instance UID -- derived from the series UID (or
        // its own uuid, if the series carries no real DICOM series_uid) so
        // every frame's synthetic file still has a distinct, stable
        // identity, as any real DICOM instance would.
        $seriesUidBase = $series->series_uid ?: $series->uuid;
        $sopInstanceUid = substr($seriesUidBase, 0, 54).'.'.$frameIndex;
        $transferSyntaxUid = $series->transfer_syntax_uid ?: '1.2.840.10008.1.2.1';
        $sopClassUid = $series->sop_class_uid ?: '1.2.840.10008.5.1.4.1.1.7';

        $metaRest = '';
        $metaRest .= $writeElement('00020002', 'UI', $sopClassUid);
        $metaRest .= $writeElement('00020003', 'UI', $sopInstanceUid);
        $metaRest .= $writeElement('00020010', 'UI', $transferSyntaxUid);
        $metaRest .= $writeElement('00020012', 'UI', '1.2.826.0.1.3680043.9.7433.1.1');
        $metaGroupLength = $writeElement('00020000', 'UL', pack('V', strlen($metaRest)));

        // Constant-orientation, linearly-spaced-in-Z stack: real per-frame
        // patient positions live in a Per-Frame Functional Groups Sequence
        // this reader doesn't parse (out of scope -- see the migration's
        // comment on is_frame_extractable). This keeps inter-frame spacing
        // correct (what MPR/volume reconstruction actually depends on) even
        // though the absolute X/Y position is a placeholder.
        $sliceThickness = $series->slice_thickness ?: 1.0;
        $imagePositionPatient = sprintf('0\\0\\%s', $frameIndex * $sliceThickness);

        $elements = '';
        $elements .= $writeElement('00080016', 'UI', $sopClassUid);
        $elements .= $writeElement('00080018', 'UI', $sopInstanceUid);
        $elements .= $writeElement('0020000E', 'UI', $seriesUidBase);
        $elements .= $writeElement('00200013', 'IS', (string) ($frameIndex + 1));

        if ($series->orientation) {
            $elements .= $writeElement('00200037', 'DS', $series->orientation);
        }
        $elements .= $writeElement('00200032', 'DS', $imagePositionPatient);

        if ($series->pixel_spacing_x && $series->pixel_spacing_y) {
            $elements .= $writeElement('00280030', 'DS', "{$series->pixel_spacing_x}\\{$series->pixel_spacing_y}");
        }
        if ($series->slice_thickness) {
            $elements .= $writeElement('00180050', 'DS', (string) $series->slice_thickness);
        }

        $elements .= $writeElement('00280002', 'US', pack('v', $series->samples_per_pixel ?: 1));
        $elements .= $writeElement('00280004', 'CS', $series->photometric_interpretation ?: 'MONOCHROME2');
        $elements .= $writeElement('00280010', 'US', pack('v', $series->rows));
        $elements .= $writeElement('00280011', 'US', pack('v', $series->columns));
        $elements .= $writeElement('00280100', 'US', pack('v', $series->bits_allocated ?: 16));
        $elements .= $writeElement('00280101', 'US', pack('v', $series->bits_stored ?: ($series->bits_allocated ?: 16)));
        $elements .= $writeElement('00280102', 'US', pack('v', $series->high_bit ?: (($series->bits_stored ?: ($series->bits_allocated ?: 16)) - 1)));
        $elements .= $writeElement('00280103', 'US', pack('v', $series->pixel_representation ?: 0));

        $pixelDataVr = ($series->bits_allocated ?: 16) <= 8 ? 'OB' : 'OW';
        $elements .= $writeElement('7FE00010', $pixelDataVr, $pixelData);

        return str_repeat("\0", 128).'DICM'.$metaGroupLength.$metaRest.$elements;
    }
}
