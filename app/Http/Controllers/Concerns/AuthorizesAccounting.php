<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared gate for every accounting endpoint (fund ledger, expenses,
 * capital/withdrawals, payroll): only a company admin (system manager), an
 * accountant, or a project admin acting for support may see or manage them.
 */
trait AuthorizesAccounting
{
    protected function assertHasAccountingAccess(Request $request): void
    {
        if ($request->user()->hasAccountingAccess()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You are not authorized to access accounting.'],
        ]);
    }
}
