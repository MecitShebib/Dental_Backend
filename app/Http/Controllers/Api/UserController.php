<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
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

        $roleIds = $data['role_ids'] ?? $user->roles()->pluck('roles.id')->all();
        $permissionIds = $data['permission_ids'] ?? $user->permissions()->pluck('permissions.id')->all();

        // A company's sole System Manager is its only way back into User
        // Management -- letting that account's own edit strip its role or
        // narrow its permissions (e.g. via the buggy client that used to
        // resubmit an empty role_ids/permission_ids set on every save) would
        // permanently lock the company out of admin access. Not just a UI
        // nicety: enforced here so no client payload, buggy or malicious,
        // can weaken it.
        if ($this->isSoleCompanySystemManager($user)) {
            $systemManagerRoleId = Role::query()->where('slug', 'system_manager')->value('id');
            if ($systemManagerRoleId && ! in_array($systemManagerRoleId, $roleIds)) {
                $roleIds[] = $systemManagerRoleId;
            }
            $permissionIds = Permission::query()->pluck('id')->all();
        }

        $user->roles()->sync($roleIds);
        $user->permissions()->sync($permissionIds);
        $this->companyUserLimit->syncActiveUsers($company);

        return $this->success(UserResource::make($user->load(['roles', 'permissions', 'company', 'branch'])), 'User updated successfully.');
    }

    /**
     * "Sole" is evaluated against the user's role membership as it stands
     * before this request's role_ids/permission_ids are applied -- i.e.
     * whether they currently hold the company's only system_manager seat,
     * not whether the incoming payload would leave them as one.
     */
    protected function isSoleCompanySystemManager(User $user): bool
    {
        if (! $user->isSystemManager()) {
            return false;
        }

        return User::query()
            ->where('company_id', $user->company_id)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'system_manager'))
            ->count() === 1;
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
