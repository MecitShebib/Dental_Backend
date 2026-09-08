<?php

namespace App\Services;

/**
 * Reads only the handful of DICOM header tags this feature needs (series
 * identity + the geometry needed for MPR/volume reconstruction) -- it never
 * touches PixelData itself. Stops as soon as it reaches PixelData, since
 * everything needed always comes before it in a standard DICOM file.
 *
 * Supports Explicit VR Little Endian only (the transfer syntax used by the
 * File Meta group itself, always, and the overwhelming majority of real
 * CBCT/CT exports for the main dataset too). If a real-world export shows up
 * using Implicit VR Little Endian for its main dataset, extend readElement()
 * to branch on the Transfer Syntax UID read from tag 00020010 -- deliberately
 * not built until a real file proves it's needed (YAGNI).
 *
 * Real-world multi-frame exports (e.g. "Enhanced CT Image Storage") commonly
 * carry undefined-length Sequence (SQ) elements -- the length field is
 * 0xFFFFFFFF and the actual content is a stream of Item (FFFE,E000) /
 * Item Delimitation (FFFE,E00D) / Sequence Delimitation (FFFE,E0DD)
 * pseudo-elements, which uniquely have NO VR field of their own. Confirmed
 * against a real vendor export during Milestone 1 testing -- without this,
 * the reader misreads the Item marker's own bytes as a VR/length and
 * corrupts every read after it, eventually trying to fread() a garbage
 * multi-gigabyte length and crashing the request.
 */
class DicomTagReader
{
    private const TAG_SERIES_INSTANCE_UID = '0020000E';

    private const TAG_MODALITY = '00080060';

    private const TAG_STUDY_DATE = '00080020';

    private const TAG_STUDY_DESCRIPTION = '00081030';

    private const TAG_ROWS = '00280010';

    private const TAG_COLUMNS = '00280011';

    private const TAG_PIXEL_SPACING = '00280030';

    private const TAG_SLICE_THICKNESS = '00180050';

    private const TAG_IMAGE_ORIENTATION_PATIENT = '00200037';

    private const TAG_PIXEL_DATA = '7FE00010';

    private const TAG_TRANSFER_SYNTAX_UID = '00020010';

    private const TAG_SOP_CLASS_UID = '00080016';

    private const TAG_NUMBER_OF_FRAMES = '00280008';

    private const TAG_SAMPLES_PER_PIXEL = '00280002';

    private const TAG_PHOTOMETRIC_INTERPRETATION = '00280004';

    private const TAG_BITS_ALLOCATED = '00280100';

    private const TAG_BITS_STORED = '00280101';

    private const TAG_HIGH_BIT = '00280102';

    private const TAG_PIXEL_REPRESENTATION = '00280103';

    /** Tags this reader actually returns -- every other element's value is
     * skipped (seeked past) rather than read into memory, since a real
     * export can carry many-megabyte private/icon/overlay elements before
     * PixelData that this reader has no use for. */
    private const WANTED_TAGS = [
        self::TAG_SERIES_INSTANCE_UID,
        self::TAG_MODALITY,
        self::TAG_STUDY_DATE,
        self::TAG_STUDY_DESCRIPTION,
        self::TAG_ROWS,
        self::TAG_COLUMNS,
        self::TAG_PIXEL_SPACING,
        self::TAG_SLICE_THICKNESS,
        self::TAG_IMAGE_ORIENTATION_PATIENT,
        self::TAG_TRANSFER_SYNTAX_UID,
        self::TAG_SOP_CLASS_UID,
        self::TAG_NUMBER_OF_FRAMES,
        self::TAG_SAMPLES_PER_PIXEL,
        self::TAG_PHOTOMETRIC_INTERPRETATION,
        self::TAG_BITS_ALLOCATED,
        self::TAG_BITS_STORED,
        self::TAG_HIGH_BIT,
        self::TAG_PIXEL_REPRESENTATION,
    ];

    private const LONG_LENGTH_VRS = ['OB', 'OW', 'OF', 'OL', 'OD', 'OV', 'SQ', 'UC', 'UR', 'UT', 'UN'];

    private const ITEM_GROUP = 0xFFFE;

    public function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$path}");
        }

        try {
            return $this->parse($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function parse($handle): array
    {
        fseek($handle, 128);
        if (fread($handle, 4) !== 'DICM') {
            throw new \InvalidArgumentException('Not a valid DICOM file (missing DICM magic bytes).');
        }

        $tags = [];
        $pixelDataInfo = null;

        while (! feof($handle)) {
            $element = $this->readElement($handle, $pixelDataInfo);
            if ($element === null) {
                break;
            }

            [$tag, $value] = $element;
            if ($value !== null) {
                $tags[$tag] = $value;
            }

            if ($tag === self::TAG_PIXEL_DATA) {
                break;
            }
        }

        $pixelSpacing = $this->splitBackslash($tags[self::TAG_PIXEL_SPACING] ?? null);
        $numberOfFrames = isset($tags[self::TAG_NUMBER_OF_FRAMES]) ? (int) trim($tags[self::TAG_NUMBER_OF_FRAMES]) : 1;

        return [
            'series_uid' => $this->trimmed($tags[self::TAG_SERIES_INSTANCE_UID] ?? null),
            'modality' => $this->trimmed($tags[self::TAG_MODALITY] ?? null),
            'study_date' => $this->trimmed($tags[self::TAG_STUDY_DATE] ?? null),
            'study_description' => $this->trimmed($tags[self::TAG_STUDY_DESCRIPTION] ?? null),
            'rows' => isset($tags[self::TAG_ROWS]) ? unpack('v', $tags[self::TAG_ROWS])[1] : null,
            'columns' => isset($tags[self::TAG_COLUMNS]) ? unpack('v', $tags[self::TAG_COLUMNS])[1] : null,
            'pixel_spacing_x' => isset($pixelSpacing[0]) ? (float) $pixelSpacing[0] : null,
            'pixel_spacing_y' => isset($pixelSpacing[1]) ? (float) $pixelSpacing[1] : null,
            'slice_thickness' => isset($tags[self::TAG_SLICE_THICKNESS]) ? (float) trim($tags[self::TAG_SLICE_THICKNESS]) : null,
            'orientation' => $this->trimmed($tags[self::TAG_IMAGE_ORIENTATION_PATIENT] ?? null),
            'transfer_syntax_uid' => $this->trimmed($tags[self::TAG_TRANSFER_SYNTAX_UID] ?? null),
            'sop_class_uid' => $this->trimmed($tags[self::TAG_SOP_CLASS_UID] ?? null),
            'frame_count' => max(1, $numberOfFrames),
            'samples_per_pixel' => isset($tags[self::TAG_SAMPLES_PER_PIXEL]) ? unpack('v', $tags[self::TAG_SAMPLES_PER_PIXEL])[1] : 1,
            'photometric_interpretation' => $this->trimmed($tags[self::TAG_PHOTOMETRIC_INTERPRETATION] ?? null) ?? 'MONOCHROME2',
            'bits_allocated' => isset($tags[self::TAG_BITS_ALLOCATED]) ? unpack('v', $tags[self::TAG_BITS_ALLOCATED])[1] : 16,
            'bits_stored' => isset($tags[self::TAG_BITS_STORED]) ? unpack('v', $tags[self::TAG_BITS_STORED])[1] : null,
            'high_bit' => isset($tags[self::TAG_HIGH_BIT]) ? unpack('v', $tags[self::TAG_HIGH_BIT])[1] : null,
            'pixel_representation' => isset($tags[self::TAG_PIXEL_REPRESENTATION]) ? unpack('v', $tags[self::TAG_PIXEL_REPRESENTATION])[1] : 0,
            // Whether PixelData has a fixed byte length (native/uncompressed
            // pixel data -- frame N sits at a computable fixed offset) or an
            // undefined length (0xFFFFFFFF, always means encapsulated/
            // compressed fragments -- no fixed per-frame offset without
            // parsing the Basic Offset Table, which this reader doesn't do).
            'pixel_data_offset' => $pixelDataInfo['offset'] ?? null,
            'pixel_data_length' => $pixelDataInfo['length'] ?? null,
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array{offset: int, length: ?int}|null  $pixelDataInfo  set by
     *                                                                reference when the PixelData tag is reached
     * @return array{0: string, 1: ?string}|null tag => value, value null
     *                                           means "skipped, not needed"
     */
    private function readElement($handle, ?array &$pixelDataInfo = null): ?array
    {
        $groupBytes = fread($handle, 2);
        $elementBytes = fread($handle, 2);
        if (strlen($groupBytes) < 2 || strlen($elementBytes) < 2) {
            return null;
        }

        $group = unpack('v', $groupBytes)[1];
        $element = unpack('v', $elementBytes)[1];
        $tag = strtoupper(sprintf('%04x%04x', $group, $element));

        // Item / Item Delimitation / Sequence Delimitation pseudo-elements
        // (group FFFE) are the one DICOM exception with no VR field at all
        // -- just a plain 4-byte length. A defined length here is skippable
        // raw bytes; undefined/zero length means "keep reading the next
        // element as normal" (the loop naturally walks into -- and back out
        // of -- nested sequence content this way, no recursion needed).
        if ($group === self::ITEM_GROUP) {
            $lengthBytes = fread($handle, 4);
            if (strlen($lengthBytes) < 4) {
                return null;
            }
            $length = unpack('V', $lengthBytes)[1];
            if ($length !== 0 && $length !== 0xFFFFFFFF) {
                fseek($handle, $length, SEEK_CUR);
            }

            return [$tag, null];
        }

        $vr = fread($handle, 2);
        if (strlen($vr) < 2) {
            return null;
        }

        if (in_array($vr, self::LONG_LENGTH_VRS, true)) {
            fread($handle, 2); // reserved bytes
            $lengthBytes = fread($handle, 4);
            if (strlen($lengthBytes) < 4) {
                return null;
            }
            $length = unpack('V', $lengthBytes)[1];
        } else {
            $lengthBytes = fread($handle, 2);
            if (strlen($lengthBytes) < 2) {
                return null;
            }
            $length = unpack('v', $lengthBytes)[1];
        }

        // Capture where PixelData's actual bytes start (and how long they
        // are, when that's fixed) before anything below seeks past them --
        // this is the only information seriesFrame() needs to compute a
        // single frame's byte range later, without re-parsing the whole
        // file at request time.
        if ($tag === self::TAG_PIXEL_DATA) {
            $pixelDataInfo = [
                'offset' => ftell($handle),
                'length' => $length === 0xFFFFFFFF ? null : $length,
            ];
        }

        if ($length === 0) {
            return [$tag, ''];
        }

        // Undefined-length SQ (or, rarely, undefined-length OB/OW pixel
        // data using encapsulated fragments): its content is itself a
        // stream of ordinary elements/Items, correctly walked by simply
        // continuing the normal read loop -- it self-terminates at its own
        // Sequence Delimitation Item, handled above.
        if ($length === 0xFFFFFFFF) {
            return [$tag, null];
        }

        // Only fully read (into memory) the handful of small metadata
        // values this reader actually returns. Everything else -- private
        // tags, icon images, and PixelData itself (which can be hundreds of
        // megabytes) -- is skipped via fseek rather than fread, so a real
        // multi-hundred-MB scan never gets read into a PHP string.
        if (! in_array($tag, self::WANTED_TAGS, true)) {
            fseek($handle, $length, SEEK_CUR);

            return [$tag, null];
        }

        $value = fread($handle, $length);

        if ($value === false || strlen($value) !== $length) {
            throw new \InvalidArgumentException('Truncated DICOM file: expected '.$length.' bytes for tag '.$tag.'.');
        }

        return [$tag, $value];
    }

    private function trimmed(?string $value): ?string
    {
        return $value === null ? null : rtrim($value);
    }

    /**
     * @return string[]|null
     */
    private function splitBackslash(?string $value): ?array
    {
        return $value === null ? null : explode('\\', rtrim($value));
    }
}
