<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Keeps one invoice per patient payment in sync with it -- created the
 * moment a payment is recorded, amount/date kept current on edit, removed
 * if the payment is removed. Mirrors how FundTransactionService keeps the
 * company fund ledger in sync with expenses/capital/payroll.
 */
class InvoiceService
{
    public function createForPayment(Payment $payment): Invoice
    {
        return DB::transaction(function () use ($payment) {
            $companyId = $payment->client->company_id;

            // lockForUpdate() serializes concurrent payment creations for the
            // same company so two requests can never compute the same "next"
            // number -- invoice numbers must never collide or be reused.
            // withTrashed() is required: the unique index on (company_id,
            // invoice_number) is a plain DB constraint that doesn't exclude
            // soft-deleted rows, so a deleted invoice still permanently
            // occupies its number -- excluding trashed rows here would let
            // max() "forget" that number and immediately collide with it.
            $lastNumber = Invoice::withTrashed()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->max('invoice_number');

            return Invoice::query()->create([
                'company_id' => $companyId,
                'client_id' => $payment->client_id,
                'payment_id' => $payment->id,
                'invoice_number' => ($lastNumber ?? 0) + 1,
                'amount' => $payment->amount,
                'issued_date' => $payment->payment_date,
                'created_by' => $payment->created_by,
            ]);
        });
    }

    public function updateForPayment(Payment $payment): void
    {
        Invoice::query()
            ->where('payment_id', $payment->id)
            ->update([
                'amount' => $payment->amount,
                'issued_date' => $payment->payment_date,
            ]);
    }

    public function deleteForPayment(Payment $payment): void
    {
        Invoice::query()->where('payment_id', $payment->id)->delete();
    }
}
