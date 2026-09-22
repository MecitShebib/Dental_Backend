<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared gate for the company-wide integration/settings endpoints (WhatsApp
 * Business credentials, Zoho CRM connection, the call webhook secret, the
 * automated message templates): only a company admin (system manager), or a
 * project admin acting for support, may see or change them.
 *
 * Deliberately narrower than AuthorizesAccounting, which these used to share:
 * an accountant needs the money screens (fund ledger, expenses, payroll), not
 * the ability to rotate a webhook secret or re-point the clinic's WhatsApp
 * number. Conversely this gate must never be used for anything that exposes
 * financial data -- that stays on AuthorizesAccounting.
 */
trait AuthorizesCompanySettings
{
    protected function assertHasCompanySettingsAccess(Request $request): void
    {
        $user = $request->user();

        if ($user->isSystemManager() || $user->isProjectAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You are not authorized to manage company settings.'],
        ]);
    }
}
