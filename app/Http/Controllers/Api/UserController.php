<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CompanyUserLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit) {}

    public function index()
    {
        $users = User::with(['roles', 'permissions', 'company', 'branch'])->latest()->paginate();

        return $this->success(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request)
    {
        $this->assertCanManageUsers($request);

        $data = $request->validated();
        $company = $request->user()->company;

        if (($data['status'] ?? 'active') === 'active') {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company);
        }

        $user = User::create([
            ...collect($data)->except(['role_ids', 'permission_ids'])->all(),
            'company_id' => $request->user()->company_id,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'is_doctor' => $data['is_doctor'] ?? false,
        ]);

        $user->roles()->sync($data['role_ids'] ?? []);
        $user->permissions()->sync($data['permission_ids'] ?? []);
        $this->companyUserLimit->syncActiveUsers($company);

        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company', 'branch'])), 'User created successfully.', 201);
    }

    public function show(User $user)
    {
        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company', 'branch'])));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if ($request->user()->isNot($user)) {
            $this->assertCanManageUsers($request);
        }

        $data = $request->validated();
        $company = $user->company;

        if (($data['status'] ?? ($user->status->value ?? $user->status)) === 'active') {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update(collect($data)->except(['role_ids', 'permission_ids'])->all());
        $user->roles()->sync($data['role_ids'] ?? $user->roles()->pluck('roles.id')->all());
        $user->permissions()->sync($data['permission_ids'] ?? $user->permissions()->pluck('permissions.id')->all());
        $this->companyUserLimit->syncActiveUsers($company);

        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company', 'branch'])), 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->assertCanManageUsers($request);

        $company = $user->company;
        $user->delete();
        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return $this->success(null, 'User deleted successfully.');
    }

    protected function assertCanManageUsers(Request $request): void
    {
        if ($request->user()->isSystemManager() || $request->user()->isProjectAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['You are not authorized to manage other users.'],
        ]);
    }

    public function doctors()
    {
        $doctors = User::with(['roles', 'permissions', 'company', 'branch', 'specialty'])->where('is_doctor', true)->where('status', 'active')->get();

        return $this->success(UserResource::collection($doctors));
    }
}
