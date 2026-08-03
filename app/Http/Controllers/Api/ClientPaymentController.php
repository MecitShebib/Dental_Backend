<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\FundTransaction;
use App\Models\Payment;
use App\Services\FundTransactionService;
use App\Services\InvoiceService;

class ClientPaymentController extends Controller
{
    public function __construct(
        protected FundTransactionService $fundTransactions,
        protected InvoiceService $invoices,
    ) {}

    public function index(Client $client)
    {
        $payments = $client->payments()->with('invoice')->latest('payment_date')->paginate();

        return $this->success(PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request, Client $client)
    {
        $payment = $client->payments()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->fundTransactions->post(
            $request->user()->company,
            FundTransaction::SOURCE_PAYMENT,
            $payment->id,
            (float) $payment->amount,
            "Payment from {$client->name}",
            $payment->payment_date,
            $request->user()->id,
        );

        $this->invoices->createForPayment($payment);

        return $this->success(PaymentResource::make($payment->load('invoice')), 'Payment created successfully.', 201);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        $this->fundTransactions->updateForSource(
            FundTransaction::SOURCE_PAYMENT,
            $payment->id,
            (float) $payment->amount,
            "Payment from {$payment->client->name}",
            $payment->payment_date,
        );

        $this->invoices->updateForPayment($payment);

        return $this->success(PaymentResource::make($payment->load('invoice')), 'Payment updated successfully.');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_PAYMENT, $payment->id);
        $this->invoices->deleteForPayment($payment);

        return $this->success(null, 'Payment deleted successfully.');
    }
}
