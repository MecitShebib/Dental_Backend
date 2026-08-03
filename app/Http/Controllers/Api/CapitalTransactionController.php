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
use App\Services\FundTransactionService;
use Illuminate\Http\Request;

class CapitalTransactionController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $transactions = $request->user()->company->capitalTransactions()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest('transaction_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = CapitalTransactionResource::collection($transactions);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreCapitalTransactionRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $data = $request->validated();
        $transaction = $request->user()->company->capitalTransactions()->create([
            ...$data,
            'created_by' => $request->user()->id,
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
