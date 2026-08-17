<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Http\Controllers\Controller;
use App\Services\Clinical\DashboardStatsService;
use Illuminate\Http\Request;

/**
 * Gynevaria's own clinical Dashboard endpoint. See
 * app/Http/Controllers/Api/Gynecology/ClientController.php's docblock.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardStatsService $dashboardStats) {}

    public function stats(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $stats = $this->dashboardStats->stats(
            dateFrom: $request->date_from,
            dateTo: $request->date_to,
            doctorId: $request->doctor_id,
            branchId: $request->branch_id,
            specialtyKey: 'gynecology',
        );

        return $this->success($stats);
    }
}
