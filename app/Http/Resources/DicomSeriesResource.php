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
            'pixel_spacing_x' => $this->pixel_spacing_x,
            'pixel_spacing_y' => $this->pixel_spacing_y,
            'slice_thickness' => $this->slice_thickness,
            'orientation' => $this->orientation,
            'storage_path' => $this->storage_path,
            // Signed rather than a plain Storage URL -- slice files live on
            // the private disk (KVKK: no unauthenticated access to patient
            // scans). One URL per slice, matching XrayImageResource's
            // image_url pattern.
            'image_urls' => collect(range(0, max(0, (int) $this->slice_count - 1)))
                ->map(fn ($index) => URL::temporarySignedRoute(
                    'dicom-series.file',
                    now()->addMinutes(60),
                    ['dicomSeries' => $this->id, 'index' => $index]
                ))
                ->all(),
        ];
    }
}
