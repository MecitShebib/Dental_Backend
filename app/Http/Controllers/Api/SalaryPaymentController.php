<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryPayment\StoreSalaryPaymentRequest;
use App\Http\Requests\SalaryPayment\UpdateSalaryPaymentRequest;
use App\Http\Resources\SalaryPaymentResource;
use App\Models\FundTransaction;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\FundTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryPaymentController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $payments = $request->user()->company->salaryPayments()
            ->with('employee')
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('period_year'), fn ($q, $year) => $q->where('period_year', $year))
            ->when($request->query('period_month'), fn ($q, $month) => $q->where('period_month', $month))
            ->latest('paid_at')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = SalaryPaymentResource::collection($payments);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function show(Request $request, SalaryPayment $salaryPayment)
    {
        $this->assertHasAccountingAccess($request);

        return $this->success(SalaryPaymentResource::make($salaryPayment->load(['employee', 'settledAdvances'])));
    }

    /**
     * Records a month's salary as paid: nets the employee's base salary
     * against whatever salary advances are still outstanding, settles those
     * advances against this payment (so they can't be settled twice), and
     * posts only the remainder to the fund -- the advance itself already
     * left the fund the day it was handed out.
     */
    public function store(StoreSalaryPaymentRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $data = $request->validated();

        $employee = User::query()->find($data['user_id']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'user_id' => ['Employee not found.'],
            ]);
        }

        if ($employee->monthly_salary === null) {
            throw ValidationException::withMessages([
                'user_id' => ['This employee does not have a monthly salary defined yet.'],
            ]);
        }

        $alreadyPaid = $request->user()->company->salaryPayments()
            ->where('user_id', $employee->id)
            ->where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month'])
            ->exists();

        if ($alreadyPaid) {
            throw ValidationException::withMessages([
                'period_month' => ['This employee has already been paid for that period.'],
            ]);
        }

        $payment = DB::transaction(function () use ($request, $employee, $data) {
            $outstandingAdvances = $employee->salaryAdvances()->unsettled()->get();
            $advancesTotal = (float) $outstandingAdvances->sum('amount');
            $baseSalary = (float) $employee->monthly_salary;
            $netAmount = max($baseSalary - $advancesTotal, 0);

            $payment = $request->user()->company->salaryPayments()->create([
                'user_id' => $employee->id,
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'],
                'base_salary' => $baseSalary,
                'advances_total' => $advancesTotal,
                'net_amount' => $netAmount,
                'paid_at' => $data['paid_at'],
                'created_by' => $request->user()->id,
            ]);

            $outstandingAdvances->each->update(['settled_by_salary_payment_id' => $payment->id]);

            if ($netAmount > 0) {
                $this->fundTransactions->post(
                    $request->user()->company,
                    FundTransaction::SOURCE_SALARY_PAYMENT,
                    $payment->id,
                    -1 * $netAmount,
                    "Salary payment - {$employee->name} ({$data['period_month']}/{$data['period_year']})",
                    $data['paid_at'],
                    $request->user()->id,
                );
            }

            return $payment;
        });

        return $this->success(SalaryPaymentResource::make($payment->load(['employee', 'settledAdvances'])), 'Salary payment recorded successfully.', 201);
    }

    /**
     * Only the paid date is correctable in place -- base_salary, advances_total,
     * and net_amount are derived from the netting logic in store(), and editing
     * them directly here would desync the fund transaction and the settled
     * advances from what actually happened. To correct an amount, delete this
     * payment (which un-settles its advances) and record it again.
     */
    public function update(UpdateSalaryPaymentRequest $request, SalaryPayment $salaryPayment)
    {
        $this->assertHasAccountingAccess($request);

        $salaryPayment->update(['paid_at' => $request->validated('paid_at')]);

        if ((float) $salaryPayment->net_amount > 0) {
            $this->fundTransactions->updateForSource(
                FundTransaction::SOURCE_SALARY_PAYMENT,
                $salaryPayment->id,
                -1 * (float) $salaryPayment->net_amount,
                "Salary payment - {$salaryPayment->employee->name} ({$salaryPayment->period_month}/{$salaryPayment->period_year})",
                $salaryPayment->paid_at,
            );
        }

        return $this->success(SalaryPaymentResource::make($salaryPayment->load(['employee', 'settledAdvances'])), 'Salary payment updated successfully.');
    }

    /**
     * Reverses a mistaken payment entirely: un-settles every advance it had
     * absorbed (so they're picked up by the next payment for this employee)
     * and removes its fund transaction, before soft-deleting the record.
     */
    public function destroy(Request $request, SalaryPayment $salaryPayment)
    {
        $this->assertHasAccountingAccess($request);

        DB::transaction(function () use ($salaryPayment) {
            $salaryPayment->settledAdvances()->update(['settled_by_salary_payment_id' => null]);
            $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_SALARY_PAYMENT, $salaryPayment->id);
            $salaryPayment->delete();
        });

        return $this->success(null, 'Salary payment deleted successfully.');
    }
}
