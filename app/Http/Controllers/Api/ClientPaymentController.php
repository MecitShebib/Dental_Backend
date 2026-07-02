<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Client;
use App\Models\Payment;

class ClientPaymentController extends Controller
{
    public function index(Client $client)
    {
        $payments = $client->payments()->latest('payment_date')->paginate();

        return $this->success(PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request, Client $client)
    {
        $payment = $client->payments()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PaymentResource::make($payment), 'Payment created successfully.', 201);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(PaymentResource::make($payment), 'Payment updated successfully.');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return $this->success(null, 'Payment deleted successfully.');
    }
}
