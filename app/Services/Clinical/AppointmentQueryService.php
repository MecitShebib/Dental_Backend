<?php

namespace App\Services\Clinical;

use App\Models\Appointment;
use App\Models\Specialty;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The one place Appointment list-query scoping lives, shared by dental's own
 * AppointmentController and every per-specialty
 * Api\{Specialty}\AppointmentController -- see
 * docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * Behavior-preserving extraction of what used to be inline in
 * Api\AppointmentController::index().
 */
class AppointmentQueryService
{
    /**
     * @param  array{doctor_id?: ?int, specialty?: ?string, branch_id?: ?int, client_id?: ?int,
     *                status?: ?string, date_from?: ?string, date_to?: ?string, date?: ?string,
     *                per_page?: ?int}  $filters
     */
    public function list(array $filters): Paginator
    {
        return Appointment::query()
            ->with(['client', 'doctor'])
            ->when($filters['doctor_id'] ?? null, fn ($query) => $query->where('doctor_id', $filters['doctor_id']))
            ->when($filters['specialty'] ?? null, function ($query) use ($filters) {
                $specialtyId = Specialty::query()->where('key', $filters['specialty'])->value('id');
                $query->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId));
            })
            ->when($filters['branch_id'] ?? null, fn ($query) => $query->whereHas('client', fn ($cq) => $cq->where('branch_id', $filters['branch_id'])))
            ->when($filters['client_id'] ?? null, fn ($query) => $query->where('client_id', $filters['client_id']))
            ->when($filters['status'] ?? null, fn ($query) => $query->where('status', $filters['status']))
            ->when(
                ($filters['date_from'] ?? null) && ($filters['date_to'] ?? null),
                fn ($query) => $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]),
                fn ($query) => $query->when($filters['date'] ?? null, fn ($q) => $q->whereDate('date', $filters['date']))
            )
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }
}
