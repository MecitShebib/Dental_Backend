<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\SalaryPayment;
use App\Services\CompanyBranchLimitService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected CompanyBranchLimitService $branchLimit) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $branches = Branch::query()
            ->withCount(['users', 'clients'])
            ->orderBy('name')
            ->get();

        return $this->success(BranchResource::collection($branches));
    }

    public function store(StoreBranchRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $company = $request->user()->company;
        $this->branchLimit->assertCanHaveAnotherBranch($company);

        $branch = Branch::create([
            ...$request->validated(),
            'company_id' => $company->id,
            'status' => $request->validated('status') ?? 'active',
        ]);

        return $this->success(BranchResource::make($branch), 'Branch created successfully.', 201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $this->assertHasAccountingAccess($request);

        $branch->update($request->validated());

        return $this->success(BranchResource::make($branch), 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        $this->assertHasAccountingAccess($request);

        if ($branch->users()->exists() || $branch->clients()->exists()) {
            throw ValidationException::withMessages([
                'branch' => ['Reassign or remove this branch\'s staff and patients before deleting it.'],
            ]);
        }

        $branch->delete();

        return $this->success(null, 'Branch deleted successfully.');
    }

    /**
     * Everything a company admin wants to see about one branch at a glance:
     * today's appointments and revenue, outstanding patient debt, and this
     * month's payroll -- all attributed via Client.branch_id (appointments,
     * payments) or User.branch_id (payroll), see the branch_id migration's
     * comment for why Client is the single attribution point.
     */
    public function summary(Request $request, Branch $branch)
    {
        $this->assertHasAccountingAccess($request);

        $date = $request->query('date') ?: now()->toDateString();
        $now = now();

        $appointmentsToday = Appointment::query()
            ->whereHas('client', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereDate('date', $date)
            ->count();

        $revenueToday = round((float) Payment::query()
            ->whereHas('client', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereDate('payment_date', $date)
            ->sum('amount'), 2);

        $patientDebtsTotal = round((float) $branch->clients()
            ->withSum('treatmentCharges as total_services', 'amount')
            ->withSum('payments as total_paid', 'amount')
            ->get()
            ->sum(fn ($client) => max(0, ($client->total_services ?? 0) - ($client->total_paid ?? 0))), 2);

        $payrollThisMonth = round((float) SalaryPayment::query()
            ->whereHas('employee', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('period_year', $now->year)
            ->where('period_month', $now->month)
            ->sum('net_amount'), 2);

        return $this->success([
            'branch' => BranchResource::make($branch),
            'date' => $date,
            'appointments_today' => $appointmentsToday,
            'revenue_today' => $revenueToday,
            'patient_debts_total' => $patientDebtsTotal,
            'payroll_this_month' => $payrollThisMonth,
            'staff_count' => $branch->users()->count(),
            'clients_count' => $branch->clients()->count(),
        ]);
    }
}
