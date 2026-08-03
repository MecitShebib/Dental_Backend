<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Resources\FundTransactionResource;
use App\Services\CompanyFundService;
use Illuminate\Http\Request;

class CompanyFundController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected CompanyFundService $fund) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $transactions = $request->user()->company->fundTransactions()
            ->when($request->query('source_type'), fn ($q, $type) => $q->where('source_type', $type))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('occurred_on', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('occurred_on', '<=', $to))
            ->latest('occurred_on')
            ->latest('id')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = FundTransactionResource::collection($transactions);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function summary(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $summary = $this->fund->summary(
            $request->user()->company,
            $request->query('from'),
            $request->query('to'),
        );

        return $this->success($summary);
    }
}
