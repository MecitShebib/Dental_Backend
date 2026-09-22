<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryPayment\StoreSalaryPaymentRequest;
use App\Http\Requests\SalaryPayment\UpdateSalaryPaymentRequest;
use App\Http\Resources\SalaryPaymentResource;
use App\Models\FundTransaction;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Services\FundTransactionService;
use App\Services\SalaryPaymentCariSyncService;
use App\Services\TreatmentChargeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryPaymentController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(
        protected FundTransactionService $fundTransactions,
        protected TreatmentChargeService $treatmentCharges,
        protected SalaryPaymentCariSyncService $cariSync,
    ) {}

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
     * Records a month's salary as paid: adds a doctor's treatment commission
     * (if they have a commission_percentage set) on top of their base salary,
     * applies the result against whatever salary advances are still
     * outstanding (oldest first, and only up to what this period's earnings
     * actually cover -- see allocateAdvances()), and posts only the
     * remainder to the fund -- the advance itself already left the fund the
     * day it was handed out.
     *
     * The "already paid this period" check is repeated INSIDE the locked
     * transaction below, not just here: this first check is only a fast
     * exit for the common case, since a query outside a transaction can't be
     * the thing that actually prevents two concurrent requests from both
     * passing it before either has inserted. salary_payments' own unique
     * index (see the 2026-09-22 migration) is the real backstop, caught
     * below if both requests still somehow reach the insert.
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

        $alreadyPaidMessages = [
            'period_month' => ['This employee has already been paid for that period.'],
        ];

        if ($this->periodAlreadyPaid($request, $employee, $data)) {
            throw ValidationException::withMessages($alreadyPaidMessages);
        }

        try {
            $payment = DB::transaction(function () use ($request, $employee, $data, $alreadyPaidMessages) {
                // Re-checked here, now genuinely inside the transaction and
                // locking the rows it reads, so a second concurrent request
                // blocks on this query until the first one commits (or rolls
                // back) instead of racing past the earlier, unlocked check.
                if ($this->periodAlreadyPaid($request, $employee, $data, lock: true)) {
                    throw ValidationException::withMessages($alreadyPaidMessages);
                }

                $outstandingAdvances = $employee->salaryAdvances()
                    ->unsettled()
                    ->orderBy('advance_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $advancesTotal = round((float) $outstandingAdvances->sum(fn (SalaryAdvance $advance) => $advance->remainingAmount()), 2);
                $baseSalary = (float) $employee->monthly_salary;

                $commissionPercentage = $employee->is_doctor && $employee->commission_percentage !== null
                    ? (float) $employee->commission_percentage
                    : null;
                $treatmentRevenue = $commissionPercentage !== null
                    ? $this->treatmentCharges->sumRealizedRevenueForDoctorInMonth($employee->id, $data['period_year'], $data['period_month'])
                    : 0.0;
                $commissionAmount = $commissionPercentage !== null
                    ? round($treatmentRevenue * ($commissionPercentage / 100), 2)
                    : 0.0;

                [$netAmount, $allocations] = $this->allocateAdvances($outstandingAdvances, $baseSalary + $commissionAmount);

                $payment = $request->user()->company->salaryPayments()->create([
                    'user_id' => $employee->id,
                    'period_year' => $data['period_year'],
                    'period_month' => $data['period_month'],
                    'base_salary' => $baseSalary,
                    'treatment_revenue' => $treatmentRevenue,
                    'commission_percentage' => $commissionPercentage,
                    'commission_amount' => $commissionAmount,
                    'advances_total' => $advancesTotal,
                    'advance_allocations' => $allocations,
                    'net_amount' => $netAmount,
                    'paid_at' => $data['paid_at'],
                    'created_by' => $request->user()->id,
                ]);

                // Only now that $payment exists: stamp settled_by_salary_payment_id
                // on every advance this payment finished off completely (a
                // partially-covered advance stays unsettled -- no FK stamped --
                // so it's picked up again, further reduced, next time).
                foreach ($allocations as $allocation) {
                    $advance = $outstandingAdvances->firstWhere('id', $allocation['salary_advance_id']);
                    if ($advance && $advance->remainingAmount() <= 0.0) {
                        $advance->update(['settled_by_salary_payment_id' => $payment->id]);
                    }
                }

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
        } catch (QueryException $e) {
            if (! $this->isDuplicatePeriodViolation($e)) {
                throw $e;
            }

            throw ValidationException::withMessages($alreadyPaidMessages);
        }

        $this->cariSync->sync($payment, $request->user()->id);

        return $this->success(SalaryPaymentResource::make($payment->load(['employee', 'settledAdvances'])), 'Salary payment recorded successfully.', 201);
    }

    protected function periodAlreadyPaid(Request $request, User $employee, array $data, bool $lock = false): bool
    {
        $query = $request->user()->company->salaryPayments()
            ->where('user_id', $employee->id)
            ->where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    /**
     * Applies $earnings to $advances oldest-first, each only up to its own
     * remaining balance, and returns [netAmount, allocations]. netAmount is
     * whatever's left of $earnings once every advance it could fully or
     * partially cover has been paid down -- identical to the old
     * `max($earnings - $advancesTotal, 0)` when $earnings covers every
     * advance in full, but now also correct when it doesn't: the shortfall
     * stays on the advance(s) it couldn't reach instead of being marked paid
     * anyway. Advances are mutated (settled_amount) but not saved here --
     * the caller saves them (and decides settled_by_salary_payment_id) once
     * $payment exists.
     *
     * @param  \Illuminate\Support\Collection<int, SalaryAdvance>  $advances
     * @return array{0: float, 1: array<int, array{salary_advance_id: int, amount: float}>}
     */
    protected function allocateAdvances($advances, float $earnings): array
    {
        $remaining = round($earnings, 2);
        $allocations = [];

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $owed = $advance->remainingAmount();
            if ($owed <= 0) {
                continue;
            }

            $applied = min($owed, $remaining);
            $remaining = round($remaining - $applied, 2);
            $advance->settled_amount = round((float) $advance->settled_amount + $applied, 2);
            $advance->save();

            $allocations[] = ['salary_advance_id' => $advance->id, 'amount' => $applied];
        }

        return [max($remaining, 0.0), $allocations];
    }

    /**
     * SQLSTATE 23000 (integrity constraint violation) is portable across the
     * sqlite driver tests run on and the mysql driver production uses. Safe
     * to treat broadly within this one narrow call site -- store()'s
     * transaction only ever inserts into salary_payments, so the only unique
     * constraint that could fire here is salary_payments_period_unique.
     */
    protected function isDuplicatePeriodViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
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

        $this->cariSync->sync($salaryPayment, $request->user()->id);

        return $this->success(SalaryPaymentResource::make($salaryPayment->load(['employee', 'settledAdvances'])), 'Salary payment updated successfully.');
    }

    /**
     * Reverses a mistaken payment entirely: puts back exactly the amount
     * this payment applied to each advance it touched (advance_allocations,
     * see allocateAdvances()) -- not a blanket "unsettle everything
     * settledAdvances() can see", which would also strip a LATER payment's
     * own contribution to an advance this one only partially covered. Then
     * removes its fund transaction, before soft-deleting the record.
     */
    public function destroy(Request $request, SalaryPayment $salaryPayment)
    {
        $this->assertHasAccountingAccess($request);

        DB::transaction(function () use ($salaryPayment) {
            $touchedAdvanceIds = [];

            foreach ((array) $salaryPayment->advance_allocations as $allocation) {
                $advance = SalaryAdvance::query()->find($allocation['salary_advance_id'] ?? null);
                if (! $advance) {
                    continue;
                }

                $touchedAdvanceIds[] = $advance->id;
                $advance->settled_amount = max(round((float) $advance->settled_amount - (float) $allocation['amount'], 2), 0.0);
                if ($advance->settled_by_salary_payment_id === $salaryPayment->id) {
                    $advance->settled_by_salary_payment_id = null;
                }
                $advance->save();
            }

            // Payments from before advance_allocations existed (backfilled by
            // the 2026-09-22 migration from their settled_by_salary_payment_id
            // relation) are covered by the loop above like any other. This is
            // only a safety net for the case where a payment genuinely
            // touched no advances at all.
            if (empty($touchedAdvanceIds)) {
                $salaryPayment->settledAdvances()->update(['settled_by_salary_payment_id' => null]);
            }

            $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_SALARY_PAYMENT, $salaryPayment->id);
            $this->cariSync->remove($salaryPayment);
            $salaryPayment->delete();
        });

        return $this->success(null, 'Salary payment deleted successfully.');
    }
}
