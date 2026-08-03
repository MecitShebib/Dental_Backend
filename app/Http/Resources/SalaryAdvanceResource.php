<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name),
            'amount' => (float) $this->amount,
            'advance_date' => $this->advance_date?->format('Y-m-d'),
            'note' => $this->note,
            'settled' => $this->settled_by_salary_payment_id !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
