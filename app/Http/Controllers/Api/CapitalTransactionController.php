<?php

namespace App\Http\Controllers\Api;

use App\Enums\CapitalTransactionType;
use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\CapitalTransaction\StoreCapitalTransactionRequest;
use App\Http\Requests\CapitalTransaction\UpdateCapitalTransactionRequest;
use App\Http\Resources\CapitalTransactionResource;
use App\Models\CapitalTransaction;
use App\Models\FundTransaction;
use App\Models\Specialty;
use App\Services\FundTransactionService;
use Illuminate\Http\Request;

class CapitalTransactionController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $actingUser = $request->user();
        $branchId = $actingUser->is_doctor && $actingUser->branch_id ? $actingUser->branch_id : $request->query('branch_id');
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $transactions = $actingUser->company->capitalTransactions()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            // A transaction with no branch_id/specialty_id assigned yet
            // (pre-dates this scoping) stays visible from every branch/
            // specialty rather than silently disappearing.
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->when($specialtyId, fn ($q) => $q->where(fn ($q2) => $q2->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->latest('transaction_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = CapitalTransactionResource::collection($transactions);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreCapitalTransactionRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $actingUser = $request->user();
        $data = $request->validated();

        $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);
        $specialtyId = $actingUser->is_doctor ? $actingUser->specialty_id : ($data['specialty_id'] ?? null);

        $transaction = $actingUser->company->capitalTransactions()->create([
            ...$data,
            'branch_id' => $branchId,
            'specialty_id' => $specialtyId,
            'created_by' => $actingUser->id,
        ]);

        $this->fundTransactions->post(
            $request->user()->company,
            FundTransaction::SOURCE_CAPITAL,
            $transaction->id,
            $this->signedAmount($transaction),
            $transaction->description ?: $this->defaultDescription($transaction),
            $transaction->transaction_date,
            $request->user()->id,
        );

        return $this->success(CapitalTransactionResource::make($transaction), 'Capital transaction recorded successfully.', 201);
    }

    public function update(UpdateCapitalTransactionRequest $request, CapitalTransaction $capitalTransaction)
    {
        $this->assertHasAccountingAccess($request);

        $capitalTransaction->update($request->validated());

        $this->fundTransactions->updateForSource(
            FundTransaction::SOURCE_CAPITAL,
            $capitalTransaction->id,
            $this->signedAmount($capitalTransaction),
            $capitalTransaction->description ?: $this->defaultDescription($capitalTransaction),
            $capitalTransaction->transaction_date,
        );

        return $this->success(CapitalTransactionResource::make($capitalTransaction), 'Capital transaction updated successfully.');
    }

    public function destroy(Request $request, CapitalTransaction $capitalTransaction)
    {
        $this->assertHasAccountingAccess($request);

        $capitalTransaction->delete();
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_CAPITAL, $capitalTransaction->id);

        return $this->success(null, 'Capital transaction deleted successfully.');
    }

    protected function signedAmount(CapitalTransaction $transaction): float
    {
        return $transaction->type === CapitalTransactionType::Injection
            ? (float) $transaction->amount
            : -1 * (float) $transaction->amount;
    }

    protected function defaultDescription(CapitalTransaction $transaction): string
    {
        $label = $transaction->type === CapitalTransactionType::Injection ? 'Capital injection' : 'Owner withdrawal';

        return $transaction->party_name ? "{$label} - {$transaction->party_name}" : $label;
    }
}
