<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAccounting;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateEmployeeSalaryRequest;
use App\Http\Resources\EmployeeSalaryResource;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeSalaryController extends Controller
{
    use AuthorizesAccounting;

    public function index(Request $request)
    {
        $this->assertHasAccountingAccess($request);

        $employees = User::query()->where('status', 'active')->orderBy('name')->get();

        return $this->success(EmployeeSalaryResource::collection($employees));
    }

    public function update(UpdateEmployeeSalaryRequest $request, User $user)
    {
        $this->assertHasAccountingAccess($request);

        $user->update(['monthly_salary' => $request->validated('monthly_salary')]);

        return $this->success(EmployeeSalaryResource::make($user), 'Employee salary updated successfully.');
    }
}
