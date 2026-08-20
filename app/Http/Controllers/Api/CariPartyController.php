<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\CariParty\StoreCariPartyRequest;
use App\Http\Requests\CariParty\UpdateCariPartyRequest;
use App\Http\Resources\CariPartyResource;
use App\Models\CariParty;
use App\Services\CariLedgerService;
use Illuminate\Http\Request;

class CariPartyController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected CariLedgerService $cariLedger) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $parties = $request->user()->company->cariParties()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return $this->success(CariPartyResource::collection($parties));
    }

    public function store(StoreCariPartyRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        // is_active is set explicitly (not left to the migration's DB-level
        // default) because a freshly create()'d model's in-memory instance
        // never re-reads column defaults it didn't insert itself -- leaving
        // it out made the *create* response's is_active read back as false
        // (null cast to bool) even though the DB row correctly stored true.
        $party = $request->user()->company->cariParties()->create([
            ...$request->validated(),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return $this->success(CariPartyResource::make($party), 'Party recorded successfully.', 201);
    }

    public function update(UpdateCariPartyRequest $request, CariParty $cariParty)
    {
        $this->assertHasAccountingAccess($request);

        $cariParty->update($request->validated());

        return $this->success(CariPartyResource::make($cariParty), 'Party updated successfully.');
    }

    public function destroy(Request $request, CariParty $cariParty)
    {
        $this->assertHasAccountingAccess($request);

        $cariParty->delete();

        return $this->success(null, 'Party deleted successfully.');
    }

    public function summary(Request $request, CariParty $cariParty)
    {
        $this->assertHasAccountingAccess($request);

        return $this->success($this->cariLedger->summary($cariParty));
    }
}
