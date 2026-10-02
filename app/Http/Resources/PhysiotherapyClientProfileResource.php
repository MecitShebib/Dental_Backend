<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhysiotherapyClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'diagnosis' => $this->diagnosis,
            'referring_physician' => $this->referring_physician,
            'affected_region' => $this->affected_region,
            'side' => $this->side,
            'prescribed_session_count' => $this->prescribed_session_count,
            'home_exercise_program' => $this->home_exercise_program,
        ];
    }
}
