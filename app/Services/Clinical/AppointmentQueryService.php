<?php

namespace App\Services\Clinical;

use App\Models\Appointment;
use App\Models\Specialty;
use App\Models\User;
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
    public function list(User $actingUser, array $filters): Paginator
    {
        $isDoctorOnly = $actingUser->isDoctorOnly();

        // A plain doctor only ever sees their own schedule -- overrides
        // whatever doctor_id the request asked for, the same rule
        // ClientQueryService::list() already enforces for patients. A
        // doctor who is also a system manager is not hard-scoped -- see
        // User::isDoctorOnly().
        $doctorId = $isDoctorOnly ? $actingUser->id : ($filters['doctor_id'] ?? null);

        // Same rule, for branch: a doctor with their own branch_id set is
        // always hard-scoped to it, regardless of what branch_id the
        // request asked for.
        $branchId = $isDoctorOnly && $actingUser->branch_id
            ? $actingUser->branch_id
            : ($filters['branch_id'] ?? null);

        return Appointment::query()
            ->with(['client', 'doctor'])
            ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->when($filters['specialty'] ?? null, function ($query) use ($filters) {
                $specialtyId = Specialty::query()->where('key', $filters['specialty'])->value('id');
                $query->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId));
            })
            // A client with no branch_id assigned yet (pre-dates branch scoping)
            // stays visible from every branch rather than silently disappearing.
            ->when($branchId, fn ($query) => $query->whereHas('client', fn ($cq) => $cq->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))))
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
