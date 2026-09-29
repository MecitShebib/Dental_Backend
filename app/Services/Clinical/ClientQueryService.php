<?php

namespace App\Services\Clinical;

use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  string|null  $specialtyKey  Only applied for a non-doctor acting user -- a plain doctor
     *                                     is always hard-scoped to their own specialty_id
     *                                     (Doctovaria Phase 8), regardless of this value. A doctor who
     *                                     is *also* a system manager is NOT hard-scoped -- see
     *                                     User::isDoctorOnly(). Also which of the profile filters below
     *                                     ($filters) actually apply, via applyProfileFilters().
     * @param  array{name?: ?string, phone?: ?string, branch_id?: ?int, per_page?: ?int, gender?: ?string,
     *                age_min?: ?int, age_max?: ?int, appointment_from?: ?string, appointment_to?: ?string,
     *                blood_type?: ?string, menopause_status?: ?string, chronic_condition?: ?string,
     *                smoking?: ?string, affected_region?: ?string, side?: ?string,
     *                fitzpatrick_skin_type?: ?string, keloid_tendency?: ?bool, dietary_type?: ?string,
     *                feeding_type?: ?string, primary_diagnosis?: ?string, asa_score?: ?string}  $filters
     */
    public function list(User $actingUser, ?string $specialtyKey, array $filters): Paginator
    {
        $isDoctorOnly = $actingUser->isDoctorOnly();

        // Same rule as the specialty scoping above: a doctor with their own
        // branch_id set is always hard-scoped to it, regardless of what
        // branch_id the request asked for.
        $branchId = $isDoctorOnly && $actingUser->branch_id
            ? $actingUser->branch_id
            : ($filters['branch_id'] ?? null);

        return Client::query()
            ->with(['branch', ...$this->nextAppointmentEagerLoad()])
            ->when($isDoctorOnly, fn ($query) => $query->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $actingUser->specialty_id)->where('primary_doctor_id', $actingUser->id)
            ))
            ->when(! $isDoctorOnly && $specialtyKey, function ($query) use ($specialtyKey) {
                $specialtyId = Specialty::query()->where('key', $specialtyKey)->value('id');
                $query->whereHas('specialtyRecords', fn ($sq) => $sq->where('specialty_id', $specialtyId));
            })
            // Optional "patients of this doctor" filter for non-doctor staff
            // (e.g. the Patients page's doctor picker). A plain doctor is
            // already hard-scoped to their own patients above, so it's
            // ignored for them -- a doctor who is also a system manager can
            // use it like any other admin, including to pick themselves.
            ->when(! $isDoctorOnly && ($filters['doctor_id'] ?? null), function ($query) use ($filters, $specialtyKey) {
                $specialtyId = $specialtyKey ? Specialty::query()->where('key', $specialtyKey)->value('id') : null;
                $query->whereHas('specialtyRecords', fn ($sq) => $sq
                    ->where('primary_doctor_id', $filters['doctor_id'])
                    ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId)));
            })
            // A client with no branch_id assigned yet (pre-dates branch scoping)
            // stays visible from every branch rather than silently disappearing.
            ->when($branchId, fn ($query) => $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->when($filters['name'] ?? null, fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when($filters['phone'] ?? null, fn ($query) => $query->where('phone', 'like', '%'.$filters['phone'].'%'))
            ->when($filters['gender'] ?? null, fn ($query) => $query->where('gender', $filters['gender']))
            ->when($filters['age_min'] ?? null, fn ($query) => $query->where('age', '>=', $filters['age_min']))
            ->when($filters['age_max'] ?? null, fn ($query) => $query->where('age', '<=', $filters['age_max']))
            ->when(
                ($filters['appointment_from'] ?? null) || ($filters['appointment_to'] ?? null),
                fn ($query) => $query->whereHas('appointments', fn ($sq) => $sq
                    ->when($filters['appointment_from'] ?? null, fn ($q) => $q->whereDate('date', '>=', $filters['appointment_from']))
                    ->when($filters['appointment_to'] ?? null, fn ($q) => $q->whereDate('date', '<=', $filters['appointment_to'])))
            )
            ->when($specialtyKey, fn ($query) => $this->applyProfileFilters($query, $specialtyKey, $filters))
            ->latest()
            ->paginate($filters['per_page'] ?? null)
            ->withQueryString();
    }

    /**
     * The "Filter" popup's per-specialty clinical fields (Patients page) --
     * each one only means something for its own specialty's profile model,
     * so a filter key a request happens to send for the wrong specialty is
     * silently ignored rather than validated away in IndexClientRequest
     * (which has no per-specialty context to do that with).
     */
    private function applyProfileFilters(Builder $query, string $specialtyKey, array $filters): void
    {
        match ($specialtyKey) {
            Specialty::GYNECOLOGY => $query
                ->when($filters['blood_type'] ?? null, fn ($q) => $q->whereHas('gynecologyProfile', fn ($sq) => $sq->where('blood_type', $filters['blood_type'])))
                ->when($filters['menopause_status'] ?? null, fn ($q) => $q->whereHas('gynecologyProfile', fn ($sq) => $sq->where('menopause_status', $filters['menopause_status']))),
            Specialty::INTERNAL_MEDICINE => $query
                ->when($filters['chronic_condition'] ?? null, fn ($q) => $q->whereHas('internalMedicineProfile', fn ($sq) => $sq->whereJsonContains('chronic_conditions', $filters['chronic_condition'])))
                ->when($filters['smoking'] ?? null, fn ($q) => $q->whereHas('internalMedicineProfile', fn ($sq) => $sq->where('smoking', $filters['smoking']))),
            Specialty::ORTHOPEDICS => $query
                ->when($filters['affected_region'] ?? null, fn ($q) => $q->whereHas('orthopedicsProfile', fn ($sq) => $sq->where('affected_region', $filters['affected_region'])))
                ->when($filters['side'] ?? null, fn ($q) => $q->whereHas('orthopedicsProfile', fn ($sq) => $sq->where('side', $filters['side']))),
            Specialty::COSMETIC => $query
                ->when($filters['fitzpatrick_skin_type'] ?? null, fn ($q) => $q->whereHas('cosmeticProfile', fn ($sq) => $sq->where('fitzpatrick_skin_type', $filters['fitzpatrick_skin_type'])))
                ->when(array_key_exists('keloid_tendency', $filters) && $filters['keloid_tendency'] !== null, fn ($q) => $q->whereHas('cosmeticProfile', fn ($sq) => $sq->where('keloid_tendency', $filters['keloid_tendency']))),
            Specialty::NUTRITION => $query
                ->when($filters['chronic_condition'] ?? null, fn ($q) => $q->whereHas('nutritionProfile', fn ($sq) => $sq->whereJsonContains('chronic_conditions', $filters['chronic_condition'])))
                ->when($filters['dietary_type'] ?? null, fn ($q) => $q->whereHas('nutritionProfile', fn ($sq) => $sq->where('dietary_type', $filters['dietary_type']))),
            Specialty::PEDIATRICS => $query
                ->when($filters['feeding_type'] ?? null, fn ($q) => $q->whereHas('pediatricsProfile', fn ($sq) => $sq->where('feeding_type', $filters['feeding_type'])))
                ->when($filters['blood_type'] ?? null, fn ($q) => $q->whereHas('pediatricsProfile', fn ($sq) => $sq->where('blood_type', $filters['blood_type']))),
            Specialty::PHYSIOTHERAPY => $query
                ->when($filters['affected_region'] ?? null, fn ($q) => $q->whereHas('physiotherapyProfile', fn ($sq) => $sq->where('affected_region', $filters['affected_region'])))
                ->when($filters['side'] ?? null, fn ($q) => $q->whereHas('physiotherapyProfile', fn ($sq) => $sq->where('side', $filters['side']))),
            Specialty::HEMATOLOGY => $query
                ->when($filters['blood_type'] ?? null, fn ($q) => $q->whereHas('hematologyProfile', fn ($sq) => $sq->where('blood_type', $filters['blood_type'])))
                ->when($filters['primary_diagnosis'] ?? null, fn ($q) => $q->whereHas('hematologyProfile', fn ($sq) => $sq->where('primary_diagnosis', $filters['primary_diagnosis']))),
            Specialty::GENERAL_SURGERY => $query
                ->when($filters['asa_score'] ?? null, fn ($q) => $q->whereHas('generalSurgeryProfile', fn ($sq) => $sq->where('asa_score', $filters['asa_score'])))
                ->when($filters['blood_type'] ?? null, fn ($q) => $q->whereHas('generalSurgeryProfile', fn ($sq) => $sq->where('blood_type', $filters['blood_type']))),
            Specialty::GENERAL_PRACTICE => $query
                ->when($filters['chronic_condition'] ?? null, fn ($q) => $q->whereHas('generalPracticeProfile', fn ($sq) => $sq->whereJsonContains('chronic_conditions', $filters['chronic_condition'])))
                ->when($filters['smoking'] ?? null, fn ($q) => $q->whereHas('generalPracticeProfile', fn ($sq) => $sq->where('smoking', $filters['smoking']))),
            default => null,
        };
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
