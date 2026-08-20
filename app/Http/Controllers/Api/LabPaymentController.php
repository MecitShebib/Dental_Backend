<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabPayment\StoreLabPaymentRequest;
use App\Http\Resources\LabPaymentResource;
use App\Models\LabCase;
use App\Models\LabPayment;
use App\Services\LabPaymentCostSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabPaymentController extends Controller
{
    public function __construct(protected LabPaymentCostSyncService $costSync) {}

    public function index(LabCase $labCase)
    {
        $payments = $labCase->labPayments()->latest('payment_date')->get();

        return $this->success(LabPaymentResource::collection($payments));
    }

    public function store(StoreLabPaymentRequest $request, LabCase $labCase)
    {
        $data = $request->validated();

        if ($labCase->lab_cost !== null && (float) $data['amount'] > $labCase->remainingBalance()) {
            throw ValidationException::withMessages([
                'amount' => ["This payment exceeds the case's remaining balance of ".$labCase->remainingBalance().'.'],
            ]);
        }

        $labPayment = $labCase->labPayments()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        $this->costSync->record($labPayment, $request->user()->id);

        return $this->success(LabPaymentResource::make($labPayment), 'Lab payment recorded successfully.', 201);
    }

    public function destroy(Request $request, LabPayment $labPayment)
    {
        $this->costSync->remove($labPayment);
        $labPayment->delete();

        return $this->success(null, 'Lab payment deleted successfully.');
    }
}
