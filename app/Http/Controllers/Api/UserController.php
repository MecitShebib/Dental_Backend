<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
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
        $users = User::with(['roles', 'permissions', 'company'])->latest()->paginate();

        return $this->success(UserResource::collection($users));
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

        $user->roles()->sync($data['role_ids'] ?? []);
        $user->permissions()->sync($data['permission_ids'] ?? []);
        $this->companyUserLimit->syncActiveUsers($company);

        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company'])), 'User created successfully.', 201);
    }

    public function show(User $user)
    {
        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company'])));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $oldCompany = $user->company;
        $company = Company::findOrFail($data['company_id'] ?? $user->company_id);

        if (($data['status'] ?? ($user->status->value ?? $user->status)) === 'active') {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update(collect($data)->except(['role_ids', 'permission_ids'])->all());
        $user->roles()->sync($data['role_ids'] ?? $user->roles()->pluck('roles.id')->all());
        $user->permissions()->sync($data['permission_ids'] ?? $user->permissions()->pluck('permissions.id')->all());
        if ($oldCompany && $oldCompany->id !== $company->id) {
            $this->companyUserLimit->syncActiveUsers($oldCompany);
        }
        $this->companyUserLimit->syncActiveUsers($company);

        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company'])), 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $company = $user->company;
        $user->delete();
        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return $this->success(null, 'User deleted successfully.');
    }

    public function doctors()
    {
        $doctors = User::with(['roles', 'permissions', 'company'])->where('is_doctor', true)->where('status', 'active')->get();

        return $this->success(UserResource::collection($doctors));
    }
}
