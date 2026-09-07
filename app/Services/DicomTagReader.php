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

        while (! feof($handle)) {
            $element = $this->readElement($handle);
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
        ];
    }

    /**
     * @param  resource  $handle
     * @return array{0: string, 1: ?string}|null tag => value, value null
     *                                           means "skipped, not needed"
     */
    private function readElement($handle): ?array
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
