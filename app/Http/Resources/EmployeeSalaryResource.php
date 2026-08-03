<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'job_title' => $this->job_title,
            'monthly_salary' => $this->monthly_salary !== null ? (float) $this->monthly_salary : null,
        ];
    }
}
