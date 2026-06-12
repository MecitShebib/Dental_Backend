<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'exists:users,id'],
        ]);

        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;
        $doctorId = $request->doctor_id;

        // --- Appointment aggregate ---
        $apptBase = Appointment::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('type', '!=', 'unavailable')
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId));

        $total    = (clone $apptBase)->count();
        $byStatus = (clone $apptBase)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $statusKeys = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];
        $appointmentsByStatus = array_combine(
            $statusKeys,
            array_map(fn ($k) => (int) ($byStatus[$k] ?? 0), $statusKeys)
        );

        // --- Income aggregate ---
        $payBase = Payment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->when($doctorId, function ($q) use ($doctorId) {
                $q->whereHas('visit', function ($vq) use ($doctorId) {
                    $vq->where('doctor_id', $doctorId);
                });
            });

        $incomeTotal = (float) (clone $payBase)->sum('amount');

        $byMethod = (clone $payBase)
            ->selectRaw('payment_method, sum(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $byDay = (clone $payBase)
            ->selectRaw('DATE(payment_date) as date, sum(amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'amount' => (float) $row->amount])
            ->values()
            ->toArray();

        return $this->success([
            'appointments' => [
                'total'     => $total,
                'by_status' => $appointmentsByStatus,
            ],
            'income' => [
                'total'     => $incomeTotal,
                'by_method' => $byMethod,
                'by_day'    => $byDay,
            ],
        ]);
    }
}
