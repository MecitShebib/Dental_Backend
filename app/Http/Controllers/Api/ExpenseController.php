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

        $expenses = $request->user()->company->expenses()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('expense_date', '<=', $to))
            ->latest('expense_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = ExpenseResource::collection($expenses);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $data = $request->validated();
        $cari = $this->extractCariInput($data);
        $data['attachment_path'] = $request->hasFile('attachment')
            ? $request->file('attachment')->store('expense-attachments', 'public')
            : null;

        $expense = $request->user()->company->expenses()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
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
                Storage::disk('public')->delete($expense->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('expense-attachments', 'public');
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
     * Pulls the optional cari-hesap fields out of the validated payload
     * before it's mass-assigned to Expense (which doesn't have these
     * columns) and returns them for syncCari() to act on afterward.
     */
    protected function extractCariInput(array &$data): array
    {
        $cari = [
            'partyable_type' => $data['cari_partyable_type'] ?? null,
            'partyable_id' => $data['cari_partyable_id'] ?? null,
            'currency' => $data['cari_currency'] ?? CariCurrency::TRY->value,
            'exchange_rate' => $data['cari_exchange_rate'] ?? 1,
        ];

        unset($data['cari_partyable_type'], $data['cari_partyable_id'], $data['cari_currency'], $data['cari_exchange_rate']);

        return $cari;
    }

    /**
     * Re-derives this expense's cari entry from scratch on every save --
     * simpler and safer than patching a possibly-different party in place,
     * and mirrors LabCaseCariSyncService's delete-then-repost pattern.
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
            $cari['currency'],
            (float) $cari['exchange_rate'],
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
