<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\CariTransaction\StoreCariTransactionRequest;
use App\Http\Requests\CariTransaction\UpdateCariTransactionRequest;
use App\Http\Resources\CariTransactionResource;
use App\Models\CariTransaction;
use App\Services\CariLedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CariTransactionController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected CariLedgerService $cariLedger) {}

    /**
     * "Hesap Hareketleri": the movement history for one partyable (a
     * CariParty, or -- for the doctor/staff and lab categories, which reuse
     * existing records instead of duplicating them -- a User or LabPartner).
     */
    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $partyable = $this->resolvePartyable($request);

        $transactions = CariTransaction::query()
            ->where('partyable_type', $partyable->getMorphClass())
            ->where('partyable_id', $partyable->getKey())
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('invoice_date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('invoice_date', '<=', $to))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = CariTransactionResource::collection($transactions);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function summary(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $partyable = $this->resolvePartyable($request);

        return $this->success($this->cariLedger->summary($partyable));
    }

    public function store(StoreCariTransactionRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        $partyable = $this->resolvePartyable($request);
        $data = $request->validated();

        $transaction = $this->cariLedger->post(
            $request->user()->company,
            $partyable,
            (float) ($data['debit'] ?? 0),
            (float) ($data['credit'] ?? 0),
            $data['currency'],
            (float) ($data['exchange_rate'] ?? 1),
            $data['transaction_type'],
            $data['description'] ?? null,
            $data['invoice_date'] ?? null,
            $data['payment_date'] ?? null,
            $data['expense_category'] ?? null,
            CariTransaction::SOURCE_MANUAL,
            null,
            $data['reference_number'] ?? null,
            $request->user()->id,
        );

        return $this->success(CariTransactionResource::make($transaction), 'Transaction recorded successfully.', 201);
    }

    public function update(UpdateCariTransactionRequest $request, CariTransaction $cariTransaction)
    {
        $this->assertHasAccountingAccess($request);
        $this->assertEditable($cariTransaction);

        $cariTransaction->update($request->validated());

        return $this->success(CariTransactionResource::make($cariTransaction), 'Transaction updated successfully.');
    }

    public function destroy(Request $request, CariTransaction $cariTransaction)
    {
        $this->assertHasAccountingAccess($request);
        $this->assertEditable($cariTransaction);

        $cariTransaction->delete();

        return $this->success(null, 'Transaction deleted successfully.');
    }

    /**
     * Company scoping comes for free: CariParty/User/LabPartner all use
     * BelongsToCompany, so resolvePartyable() can't resolve another tenant's row.
     */
    protected function resolvePartyable(Request $request): Model
    {
        $model = $this->cariLedger->resolvePartyable(
            $request->input('partyable_type'),
            (int) $request->input('partyable_id'),
        );

        if (! $model) {
            throw ValidationException::withMessages([
                'partyable_id' => ['The selected account could not be found.'],
            ]);
        }

        return $model;
    }

    protected function assertEditable(CariTransaction $transaction): void
    {
        if ($transaction->source_type !== null && $transaction->source_type !== CariTransaction::SOURCE_MANUAL) {
            throw ValidationException::withMessages([
                'source_type' => ['This entry was posted automatically and cannot be edited or deleted directly.'],
            ]);
        }
    }
}
