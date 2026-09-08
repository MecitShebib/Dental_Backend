<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class DicomSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'series_uid' => $this->series_uid,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'slice_count' => $this->slice_count,
            'frame_count' => $this->frame_count,
            'pixel_spacing_x' => $this->pixel_spacing_x,
            'pixel_spacing_y' => $this->pixel_spacing_y,
            'slice_thickness' => $this->slice_thickness,
            'orientation' => $this->orientation,
            'storage_path' => $this->storage_path,
            // Signed rather than a plain Storage URL -- slice files live on
            // the private disk (KVKK: no unauthenticated access to patient
            // scans). A real CBCT/CT export is almost always ONE file
            // holding hundreds of frames, not one file per slice -- when
            // that single file's PixelData sits at a fixed byte offset
            // (is_frame_extractable, set at upload time by
            // DicomStudyController::store()), one URL per FRAME is minted
            // instead of one giant URL to the whole file, so the viewer
            // downloads a ~500KB-1MB frame at a time instead of the full
            // 100-500MB export just to decode a single slice of it. Falls
            // back to today's one-URL-per-uploaded-file behavior otherwise
            // (compressed pixel data, or a series genuinely made of several
            // single-frame files).
            'image_urls' => $this->is_frame_extractable
                ? collect(range(0, max(0, (int) $this->frame_count - 1)))
                    ->map(fn ($frame) => URL::temporarySignedRoute(
                        'dicom-series.frame',
                        now()->addMinutes(60),
                        ['dicomSeries' => $this->id, 'frame' => $frame]
                    ))
                    ->all()
                : collect(range(0, max(0, (int) $this->slice_count - 1)))
                    ->map(fn ($index) => URL::temporarySignedRoute(
                        'dicom-series.file',
                        now()->addMinutes(60),
                        ['dicomSeries' => $this->id, 'index' => $index]
                    ))
                    ->all(),
        ];
    }
}
