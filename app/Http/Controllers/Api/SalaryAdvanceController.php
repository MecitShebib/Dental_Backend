<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryAdvance\StoreSalaryAdvanceRequest;
use App\Http\Requests\SalaryAdvance\UpdateSalaryAdvanceRequest;
use App\Http\Resources\SalaryAdvanceResource;
use App\Models\FundTransaction;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Services\FundTransactionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceController extends Controller
{
    use AuthorizesAccounting;

    public function __construct(protected FundTransactionService $fundTransactions) {}

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $advances = $request->user()->company->salaryAdvances()
            ->with('employee')
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->boolean('unsettled_only'), fn ($q) => $q->unsettled())
            ->latest('advance_date')
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = SalaryAdvanceResource::collection($advances);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreSalaryAdvanceRequest $request)
    {
        $this->assertHasAccountingAccess($request);

        // find() (not exists:users,id) so this stays scoped to the acting
        // user's own company via User's BelongsToCompany global scope.
        $employee = User::query()->find($request->validated('user_id'));

        if (! $employee) {
            throw ValidationException::withMessages([
                'user_id' => ['Employee not found.'],
            ]);
        }

        $advance = $request->user()->company->salaryAdvances()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $this->fundTransactions->post(
            $request->user()->company,
            FundTransaction::SOURCE_SALARY_ADVANCE,
            $advance->id,
            -1 * (float) $advance->amount,
            "Salary advance - {$employee->name}",
            $advance->advance_date,
            $request->user()->id,
        );

        return $this->success(SalaryAdvanceResource::make($advance->load('employee')), 'Salary advance recorded successfully.', 201);
    }

    public function update(UpdateSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance)
    {
        $this->assertHasAccountingAccess($request);
        $this->assertUnsettled($salaryAdvance, 'edited');

        $salaryAdvance->update($request->validated());

        $this->fundTransactions->updateForSource(
            FundTransaction::SOURCE_SALARY_ADVANCE,
            $salaryAdvance->id,
            -1 * (float) $salaryAdvance->amount,
            "Salary advance - {$salaryAdvance->employee->name}",
            $salaryAdvance->advance_date,
        );

        return $this->success(SalaryAdvanceResource::make($salaryAdvance->load('employee')), 'Salary advance updated successfully.');
    }

    public function destroy(Request $request, SalaryAdvance $salaryAdvance)
    {
        $this->assertHasAccountingAccess($request);
        $this->assertUnsettled($salaryAdvance, 'removed');

        $salaryAdvance->delete();
        $this->fundTransactions->deleteForSource(FundTransaction::SOURCE_SALARY_ADVANCE, $salaryAdvance->id);

        return $this->success(null, 'Salary advance deleted successfully.');
    }

    protected function assertUnsettled(SalaryAdvance $salaryAdvance, string $action): void
    {
        if ($salaryAdvance->settled_by_salary_payment_id !== null) {
            throw ValidationException::withMessages([
                'salary_advance' => ["This advance has already been settled in a salary payment and can no longer be {$action}."],
            ]);
        }
    }
}
