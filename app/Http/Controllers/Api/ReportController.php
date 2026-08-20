<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LabPartner;
use App\Models\LabPayment;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use AuthorizesAccounting;

    /**
     * Every client whose treatment_charges total exceeds what they've paid,
     * highest balance first -- same remaining_amount formula as
     * ClientFinancialSummaryService, just across every client at once
     * instead of one at a time.
     */
    public function patientDebts(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $rows = Client::query()
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            // "this doctor's/specialty's patients" -- same ownership model
            // ClientQueryService uses for the Patients list (primary_doctor_id
            // on the client's specialty enrollment, not just "has ever had a
            // visit with").
            ->when($request->doctor_id, fn ($q) => $q->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('primary_doctor_id', $request->doctor_id)
            ))
            ->when($specialtyId, fn ($q) => $q->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $specialtyId)
            ))
            ->withSum('treatmentCharges as total_services', 'amount')
            ->withSum('payments as total_paid', 'amount')
            ->get()
            ->map(function (Client $client) {
                $totalServices = round((float) ($client->total_services ?? 0), 2);
                $totalPaid = round((float) ($client->total_paid ?? 0), 2);

                return [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'total_services_amount' => $totalServices,
                    'total_paid_amount' => $totalPaid,
                    'remaining_amount' => round($totalServices - $totalPaid, 2),
                ];
            })
            ->filter(fn (array $row) => $row['remaining_amount'] > 0)
            ->sortByDesc('remaining_amount')
            ->values();

        return $this->success($rows);
    }

    /**
     * Every lab partner the company still owes money to: sum of every case's
     * lab_cost minus every recorded LabPayment against those same cases,
     * highest balance first.
     */
    public function labDebts(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
        ]);

        $branchId = $request->branch_id;
        $doctorId = $request->doctor_id;

        $rows = LabPartner::query()
            ->with(['labCases' => function ($query) use ($branchId, $doctorId) {
                $query->whereNotNull('lab_cost')
                    ->when($branchId, fn ($q) => $q->whereHas('client', fn ($cq) => $cq->where('branch_id', $branchId)))
                    ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId));
            }])
            ->get()
            ->map(function (LabPartner $labPartner) {
                $labCaseIds = $labPartner->labCases->pluck('id');
                $totalCost = round((float) $labPartner->labCases->sum('lab_cost'), 2);
                $totalPaid = round((float) LabPayment::query()->whereIn('lab_case_id', $labCaseIds)->sum('amount'), 2);

                return [
                    'lab_partner_id' => $labPartner->id,
                    'lab_partner_name' => $labPartner->name,
                    'total_lab_cost' => $totalCost,
                    'total_paid' => $totalPaid,
                    'remaining_balance' => round($totalCost - $totalPaid, 2),
                ];
            })
            ->filter(fn (array $row) => $row['remaining_balance'] > 0)
            ->sortByDesc('remaining_balance')
            ->values();

        return $this->success($rows);
    }

    /**
     * Per-employee payroll snapshot for a given month: what they were paid
     * (base + commission) that period, and what's still owed against them
     * (unsettled salary advances) regardless of period -- an HR-facing
     * complement to the payroll/salary-payments ledger, which is per-record
     * rather than per-employee-per-month.
     */
    public function payrollSummary(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $rows = User::query()
            ->where('status', 'active')
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->doctor_id, fn ($q) => $q->where('id', $request->doctor_id))
            // Non-doctor staff (accountants, secretaries...) have no
            // specialty of their own -- a specialty filter narrows which
            // doctors show up without hiding the rest of the payroll.
            ->when($specialtyId, fn ($q) => $q->where(fn ($sub) => $sub->where('specialty_id', $specialtyId)->orWhere('is_doctor', false)))
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) use ($year, $month) {
                $payment = SalaryPayment::query()
                    ->where('user_id', $employee->id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->first();

                $unsettledAdvances = round((float) SalaryAdvance::query()
                    ->where('user_id', $employee->id)
                    ->unsettled()
                    ->sum('amount'), 2);

                return [
                    'user_id' => $employee->id,
                    'name' => $employee->name,
                    'job_title' => $employee->job_title,
                    'monthly_salary' => round((float) $employee->monthly_salary, 2),
                    'commission_percentage' => round((float) $employee->commission_percentage, 2),
                    'period_year' => $year,
                    'period_month' => $month,
                    'paid_this_period' => (bool) $payment,
                    'treatment_revenue' => round((float) ($payment->treatment_revenue ?? 0), 2),
                    'commission_amount' => round((float) ($payment->commission_amount ?? 0), 2),
                    'net_amount_this_period' => round((float) ($payment->net_amount ?? 0), 2),
                    'unsettled_advances' => $unsettledAdvances,
                ];
            })
            ->values();

        return $this->success([
            'period_year' => $year,
            'period_month' => $month,
            'employees' => $rows,
            'totals' => [
                'net_paid_this_period' => round($rows->sum('net_amount_this_period'), 2),
                'commission_this_period' => round($rows->sum('commission_amount'), 2),
                'unsettled_advances' => round($rows->sum('unsettled_advances'), 2),
            ],
        ]);
    }
}
