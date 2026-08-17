<?php

namespace App\Services\Clinical;

use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The one place Client list-query scoping lives, shared by dental's own
 * ClientController and every per-specialty Api\{Specialty}\ClientController
 * -- see docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * A behavior-preserving extraction of what used to be inline in
 * Api\ClientController::index(); do not change the scoping rules here
 * without re-reading ClientSpecialtyEnrollmentService's docblock first.
 */
class ClientQueryService
{
    /**
     * @param  string|null  $specialtyKey  Only applied for a non-doctor acting user -- a doctor
     *                                     is always hard-scoped to their own specialty_id
     *                                     (Doctovaria Phase 8), regardless of this value.
     * @param  array{name?: ?string, phone?: ?string, per_page?: ?int}  $filters
     */
    public function list(User $actingUser, ?string $specialtyKey, array $filters): Paginator
    {
        return Client::query()
            ->with($this->nextAppointmentEagerLoad())
            ->when($actingUser->is_doctor, fn ($query) => $query->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $actingUser->specialty_id)->where('primary_doctor_id', $actingUser->id)
            ))
            ->when(! $actingUser->is_doctor && $specialtyKey, function ($query) use ($specialtyKey) {
                $specialtyId = Specialty::query()->where('key', $specialtyKey)->value('id');
                $query->whereHas('specialtyRecords', fn ($sq) => $sq->where('specialty_id', $specialtyId));
            })
            ->when($filters['name'] ?? null, fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when($filters['phone'] ?? null, fn ($query) => $query->where('phone', 'like', '%'.$filters['phone'].'%'))
            ->latest()
            ->paginate($filters['per_page'] ?? null)
            ->withQueryString();
    }

    /**
     * @return array<string, \Closure>
     */
    public function nextAppointmentEagerLoad(): array
    {
        return [
            'appointments' => fn ($query) => $query->with(['client', 'doctor'])
                ->where('status', 'scheduled')
                ->whereDate('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(1),
        ];
    }
}
