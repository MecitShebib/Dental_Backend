<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name),
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'base_salary' => (float) $this->base_salary,
            'treatment_revenue' => (float) $this->treatment_revenue,
            'commission_percentage' => $this->commission_percentage !== null ? (float) $this->commission_percentage : null,
            'commission_amount' => (float) $this->commission_amount,
            'advances_total' => (float) $this->advances_total,
            'net_amount' => (float) $this->net_amount,
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
