<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FundTransaction;

class CompanyFundService
{
    public function balance(Company $company): float
    {
        return round((float) $company->fundTransactions()->sum('amount'), 2);
    }

    /**
     * Balance plus an income/expense breakdown by source type, optionally
     * scoped to a date range -- the month-end close view an admin or
     * accountant needs to see what actually moved the fund.
     */
    public function summary(Company $company, ?string $from = null, ?string $to = null): array
    {
        $query = $company->fundTransactions()->when(
            $from,
            fn ($q) => $q->whereDate('occurred_on', '>=', $from)
        )->when(
            $to,
            fn ($q) => $q->whereDate('occurred_on', '<=', $to)
        );

        $totalsBySource = (clone $query)
            ->selectRaw('source_type, SUM(amount) as total')
            ->groupBy('source_type')
            ->pluck('total', 'source_type')
            ->map(fn ($total) => round((float) $total, 2));

        $totalIn = (clone $query)->where('amount', '>', 0)->sum('amount');
        $totalOut = (clone $query)->where('amount', '<', 0)->sum('amount');

        return [
            'balance' => $this->balance($company),
            'period_total_in' => round((float) $totalIn, 2),
            'period_total_out' => round((float) abs($totalOut), 2),
            'period_net' => round((float) $totalIn + $totalOut, 2),
            'by_source' => [
                FundTransaction::SOURCE_PAYMENT => (float) ($totalsBySource[FundTransaction::SOURCE_PAYMENT] ?? 0),
                FundTransaction::SOURCE_EXPENSE => (float) ($totalsBySource[FundTransaction::SOURCE_EXPENSE] ?? 0),
                FundTransaction::SOURCE_CAPITAL => (float) ($totalsBySource[FundTransaction::SOURCE_CAPITAL] ?? 0),
                FundTransaction::SOURCE_SALARY_ADVANCE => (float) ($totalsBySource[FundTransaction::SOURCE_SALARY_ADVANCE] ?? 0),
                FundTransaction::SOURCE_SALARY_PAYMENT => (float) ($totalsBySource[FundTransaction::SOURCE_SALARY_PAYMENT] ?? 0),
            ],
        ];
    }
}
