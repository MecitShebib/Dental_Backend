<?php

namespace App\Http\Controllers\Api;

use App\Enums\CariCurrency;
use App\Enums\CariTransactionType;
use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\CariTransaction;
use App\Models\Expense;
use App\Models\FundTransaction;
use App\Models\Specialty;
use App\Services\CariLedgerService;
use App\Services\FundTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(
        protected FundTransactionService $fundTransactions,
        protected CariLedgerService $cariLedger,
    ) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $actingUser = $request->user();
        $branchId = $actingUser->is_doctor && $actingUser->branch_id ? $actingUser->branch_id : $request->query('branch_id');
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $expenses = $actingUser->company->expenses()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('expense_date', '<=', $to))
            // An expense with no branch_id/specialty_id assigned yet
            // (pre-dates this scoping) stays visible from every branch/
            // specialty rather than silently disappearing.
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->when($specialtyId, fn ($q) => $q->where(fn ($q2) => $q2->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->latest('expense_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = ExpenseResource::collection($expenses);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $actingUser = $request->user();
        $data = $request->validated();
        $cari = $this->extractCariInput($data);
        $data['attachment_path'] = $request->hasFile('attachment')
            ? $request->file('attachment')->store('expense-attachments', 'local')
            : null;

        // Same rule as everywhere else: a user with their own branch_id/
        // specialty_id (doctor) always creates records there, overriding
        // whatever the request sent.
        $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);
        $specialtyId = $actingUser->is_doctor ? $actingUser->specialty_id : ($data['specialty_id'] ?? null);

        $expense = $actingUser->company->expenses()->create([
            ...$data,
            'branch_id' => $branchId,
            'specialty_id' => $specialtyId,
            'created_by' => $actingUser->id,
            'updated_by' => $actingUser->id,
        ]);

        $this->fundTransactions->post(
            $request->user()->company,
            FundTransaction::SOURCE_EXPENSE,
            $expense->id,
            -1 * (float) $expense->amount,
            $expense->description ?: ucfirst(str_replace('_', ' ', $expense->category->value)),
            $expense->expense_date,
            $request->user()->id,
        );

        $this->syncCari($request, $expense, $cari);

        return $this->success(ExpenseResource::make($expense), 'Expense recorded successfully.', 201);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->assertHasAccountingAccess($request);

        $data = $request->validated();
        $cari = $this->extractCariInput($data);

        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path) {
                Storage::disk('local')->delete($expense->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('expense-attachments', 'local');
        }

        $expense->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->fundTransactions->updateForSource(
            FundTransaction::SOURCE_EXPENSE,
            $expense->id,
            -1 * (float) $expense->amount,
            $expense->description ?: ucfirst(str_replace('_', ' ', $expense->category->value)),
            $expense->expense_date,
        );

        $this->syncCari($request, $expense, $cari);

        return $this->success(ExpenseResource::make($expense), 'Expense updated successfully.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->assertHasAccountingAccess($request);

        $expense->delete();
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_EXPENSE, $expense->id);
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_EXPENSE, $expense->id);

        return $this->success(null, 'Expense deleted successfully.');
    }

    /**
     * Streams the attachment from the private disk. See
     * XrayImageController::file() for why this route has no bearer-token
     * check -- the signed URL itself is the authorization.
     */
    public function attachment(Expense $expense)
    {
        return Storage::disk('local')->response($expense->attachment_path);
    }

    /**
     * Pulls the optional cari-hesap fields out of the validated payload
     * before it's mass-assigned to Expense (which doesn't have these
     * columns) and returns them for syncCari() to act on afterward.
     *
     * Only the counterparty is taken from the request: the currency is not
     * the caller's to choose -- see syncCari() for why.
     */
    protected function extractCariInput(array &$data): array
    {
        $cari = [
            'partyable_type' => $data['cari_partyable_type'] ?? null,
            'partyable_id' => $data['cari_partyable_id'] ?? null,
        ];

        // Tolerated on the wire (the expense form still posts them) but
        // deliberately ignored -- older clients must not be able to
        // mislabel the row's currency.
        unset($data['cari_partyable_type'], $data['cari_partyable_id'], $data['cari_currency'], $data['cari_exchange_rate']);

        return $cari;
    }

    /**
     * Re-derives this expense's cari entry from scratch on every save --
     * simpler and safer than patching a possibly-different party in place,
     * and mirrors LabCaseCariSyncService's delete-then-repost pattern.
     *
     * The row is always posted in the company's base currency (TRY) at rate
     * 1, regardless of what the request asked for. Expense.amount has no
     * currency of its own: it is the exact figure that leaves the TRY-only
     * company fund ledger (FundTransactionService, above). The cari ledger
     * never converts between currencies either -- CariLedgerService::summary()
     * groups by currency and keeps a TRY total and a USD total side by side,
     * with exchange_rate stored but never multiplied against anything. So
     * labelling this row "USD" did not convert the amount, it just filed 100
     * TRY under the USD total as "100 USD owed" (~32x too much at a 32.00
     * rate). A genuinely foreign-currency payable belongs in a manual cari
     * entry (CariTransactionController), where the caller states the amount
     * in that currency directly.
     */
    protected function syncCari(Request $request, Expense $expense, array $cari): void
    {
        $this->cariLedger->deleteForSource(CariTransaction::SOURCE_EXPENSE, $expense->id);

        $partyable = $this->cariLedger->resolvePartyable($cari['partyable_type'], $cari['partyable_id'] ? (int) $cari['partyable_id'] : null);

        if (! $partyable) {
            return;
        }

        $this->cariLedger->post(
            $request->user()->company,
            $partyable,
            (float) $expense->amount,
            0,
            CariCurrency::TRY->value,
            1,
            CariTransactionType::Invoice->value,
            $expense->description ?: ucfirst(str_replace('_', ' ', $expense->category->value)),
            $expense->expense_date?->toDateString(),
            null,
            $expense->category->value,
            CariTransaction::SOURCE_EXPENSE,
            $expense->id,
            $expense->invoice_number,
            $request->user()->id,
        );
    }
}
