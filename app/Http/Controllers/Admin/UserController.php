<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyUserLimitService;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit)
    {
    }

    public function index()
    {
        return view('admin.users.index', [
            'users' => User::with(['roles', 'company.currentSubscription'])->latest()->get(),
            'roles' => Role::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $company = Company::findOrFail($data['company_id']);

        if (($data['status'] ?? 'active') === 'active') {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company);
        }

        $user = User::create([
            ...collect($data)->except(['role_ids', 'permission_ids'])->all(),
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'is_doctor' => $data['is_doctor'] ?? false,
        ]);

        $user->roles()->sync(empty($data['role_ids']) ? [] : [(int) $data['role_ids'][0]]);
        $user->permissions()->sync($data['permission_ids'] ?? []);
        $this->companyUserLimit->syncActiveUsers($company);

        return redirect()->route('admin.companies.show', $company)->with('status', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $oldCompany = $user->company;
        $company = Company::findOrFail($data['company_id'] ?? $user->company_id);

        if (($data['status'] ?? ($user->status->value ?? $user->status)) === 'active') {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } elseif (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update(collect($data)->except(['role_ids', 'permission_ids'])->all());
        $user->roles()->sync(empty($data['role_ids']) ? [] : [(int) $data['role_ids'][0]]);
        $user->permissions()->sync($data['permission_ids'] ?? []);
        if ($oldCompany && $oldCompany->id !== $company->id) {
            $this->companyUserLimit->syncActiveUsers($oldCompany);
        }
        $this->companyUserLimit->syncActiveUsers($company);

        return redirect()->route('admin.companies.show', $company)->with('status', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $company = $user->company;
        $user->delete();
        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User deleted successfully.');
    }

    public function toggleStatus(User $user)
    {
        $company = $user->company;
        $newStatus = ($user->status->value ?? $user->status) === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active' && $company) {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user);
        }

        $user->update(['status' => $newStatus]);

        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User status updated successfully.');
    }
}
