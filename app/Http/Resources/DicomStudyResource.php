<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DicomStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'branch_id' => $this->branch_id,
            'specialty_id' => $this->specialty_id,
            'modality' => $this->modality,
            'study_date' => $this->study_date?->toDateString(),
            'description' => $this->description,
            'slice_count' => $this->slice_count,
            'status' => $this->status,
            'series' => DicomSeriesResource::collection($this->whenLoaded('series')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
