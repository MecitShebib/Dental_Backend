<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        ];
    }
}
