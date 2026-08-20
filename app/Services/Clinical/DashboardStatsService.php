<?php

namespace App\Services\Clinical;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Specialty;
use App\Models\User;

/**
 * The one place dashboard-stats query scoping lives, shared by dental's own
 * DashboardController and every per-specialty
 * Api\{Specialty}\DashboardController -- see
 * docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * Behavior-preserving extraction of what used to be inline in
 * Api\DashboardController::stats().
 */
class DashboardStatsService
{
    public function stats(User $actingUser, string $dateFrom, string $dateTo, ?int $doctorId, ?int $branchId, ?string $specialtyKey): array
    {
        // Same rule as ClientQueryService/AppointmentQueryService: a doctor
        // only ever sees their own numbers, regardless of what doctor_id (or
        // even a mismatched specialty/branch) the request asked for.
        if ($actingUser->is_doctor) {
            $doctorId = $actingUser->id;
        }

        $specialtyId = null;
        $specialtyFilterRequested = $specialtyKey !== null;
        if ($specialtyFilterRequested) {
            $specialtyId = Specialty::query()->where('key', $specialtyKey)->value('id');
        }

        // whereDate() (not whereBetween on the raw column) because MySQL's DATE
        // column type silently truncates any time component on insert, but a
        // bare string BETWEEN comparison on a same-day range (date_from ===
        // date_to, e.g. the dashboard's "Today" filter) would otherwise be
        // sensitive to that -- whereDate() normalizes the column with SQL's
        // DATE() function first, so it compares correctly regardless of
        // whatever the underlying storage format actually is.
        $apptBase = Appointment::query()
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->where('type', '!=', 'unavailable')
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($specialtyFilterRequested, function ($q) use ($specialtyId) {
                $q->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId));
            })
            ->when($branchId, fn ($q) => $q->whereHas('client', fn ($cq) => $cq->where('branch_id', $branchId)));

        $total = (clone $apptBase)->count();
        $byStatus = (clone $apptBase)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->getRawOriginal('status') ?? $row->status?->value ?? (string) $row->status) => (int) $row->cnt,
            ])
            ->toArray();

        $statusKeys = ['scheduled', 'completed', 'cancelled', 'no_show'];
        $appointmentsByStatus = array_combine(
            $statusKeys,
            array_map(fn ($k) => (int) ($byStatus[$k] ?? 0), $statusKeys)
        );

        $payBase = Payment::query()
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->when($doctorId, function ($q) use ($doctorId) {
                $q->whereHas('visit', function ($vq) use ($doctorId) {
                    $vq->where('doctor_id', $doctorId);
                });
            })
            ->when($specialtyFilterRequested, function ($q) use ($specialtyId) {
                $q->whereHas('visit.doctor', function ($dq) use ($specialtyId) {
                    $dq->where('specialty_id', $specialtyId);
                });
            })
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('client', function ($cq) use ($branchId) {
                    $cq->where('branch_id', $branchId);
                });
            });

        $incomeTotal = (float) (clone $payBase)->sum('amount');

        $byMethod = (clone $payBase)
            ->selectRaw('payment_method, sum(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->getRawOriginal('payment_method') ?? $row->payment_method?->value ?? (string) $row->payment_method) => (float) $row->total,
            ])
            ->toArray();

        $byDay = (clone $payBase)
            ->selectRaw('DATE(payment_date) as date, sum(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'amount' => (float) $row->amount])
            ->values()
            ->toArray();

        return [
            'appointments' => [
                'total' => $total,
                'by_status' => $appointmentsByStatus,
            ],
            'income' => [
                'total' => $incomeTotal,
                'by_method' => $byMethod,
                'by_day' => $byDay,
            ],
        ];
    }
}
