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
            'is_doctor' => (bool) $this->is_doctor,
            'monthly_salary' => $this->monthly_salary !== null ? (float) $this->monthly_salary : null,
            'commission_percentage' => $this->commission_percentage !== null ? (float) $this->commission_percentage : null,
        ];
    }
}
