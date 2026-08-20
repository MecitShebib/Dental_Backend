<?php

namespace App\Services;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Models\CariTransaction;
use App\Models\SalaryPayment;

/**
 * Mirrors a SalaryPayment into the employee's cari ledger as a matched pair:
 * a debit for what was earned this period (base salary + commission, before
 * any advance netting) and a credit for what actually left the fund (any
 * advances already settled, plus this payment's net_amount) -- so the
 * ledger reads "earned X this month, paid X this month" per payment, and
 * a partially-netted period (advances exceeding earnings) still leaves a
 * visible running balance instead of silently disappearing. Delete-then-
 * repost, same idempotent pattern as LabCaseCariSyncService, since the only
 * thing that can change after creation is paid_at.
 */
class SalaryPaymentCariSyncService
{
    public function __construct(protected CariLedgerService $cariLedger) {}

    public function sync(SalaryPayment $salaryPayment, int $actingUserId): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_SALARY_PAYMENT, $salaryPayment->id);

        $salaryPayment->loadMissing(['employee.company']);
        $employee = $salaryPayment->employee;
        $earned = (float) $salaryPayment->base_salary + (float) $salaryPayment->commission_amount;
        $paidOut = (float) $salaryPayment->advances_total + (float) $salaryPayment->net_amount;
        $period = "{$salaryPayment->period_month}/{$salaryPayment->period_year}";

        if (! $employee || $earned <= 0) {
            return;
        }

        $this->cariLedger->post(
            $employee->company,
            $employee,
            $earned,
            0,
            CariCurrency::TRY->value,
            1,
            CariTransactionType::Invoice->value,
            "Salary earned — {$period}",
            $salaryPayment->paid_at?->toDateString(),
            null,
            null,
            CariTransaction::SOURCE_SALARY_PAYMENT,
            $salaryPayment->id,
            null,
            $actingUserId,
        );

        if ($paidOut > 0) {
            $this->cariLedger->post(
                $employee->company,
                $employee,
                0,
                $paidOut,
                CariCurrency::TRY->value,
                1,
                CariTransactionType::Payment->value,
                "Salary paid — {$period}",
                null,
                $salaryPayment->paid_at?->toDateString(),
                null,
                CariTransaction::SOURCE_SALARY_PAYMENT,
                $salaryPayment->id,
                null,
                $actingUserId,
            );
        }
    }

    public function remove(SalaryPayment $salaryPayment): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_SALARY_PAYMENT, $salaryPayment->id);
    }
}
