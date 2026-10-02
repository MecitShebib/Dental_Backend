<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyUserLimitService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit) {}

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
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, null, (bool) ($data['is_doctor'] ?? false));
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
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user, (bool) ($data['is_doctor'] ?? $user->is_doctor));
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
        // withTrashed(): plain $user->company (a normal BelongsTo) would
        // resolve to null if the company happens to already be soft-deleted
        // -- Company's own SoftDeletes scope excludes it from that lookup
        // same as any other query -- and redirect()->route() needs a real
        // company to build the URL.
        $company = Company::withTrashed()->find($user->company_id);
        $user->delete();
        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User deleted successfully.');
    }

    /**
     * Independent of Admin\CompanyController::restore() (which restores
     * every user a company-delete cascaded into) -- this is for restoring
     * one specific user on its own, e.g. after deleting them individually,
     * or deciding not to bring every user back when restoring their company.
     */
    public function restore(User $user)
    {
        $company = Company::withTrashed()->find($user->company_id);
        $user->restore();
        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User restored successfully.');
    }

    /**
     * The real, irreversible version of destroy() -- only reachable for an
     * already soft-deleted user (the view only shows this next to Restore;
     * the guard below protects the route itself). Appointments, visits,
     * prescriptions, and lab records all deliberately restrict-delete their
     * doctor_id -- a historical clinical record must keep naming a real
     * doctor -- so this will fail (caught below, flashed as a friendly
     * message instead of a raw 500) for any doctor who has ever had a
     * patient. That's correct, not a bug: a company's own force-delete
     * (Admin\CompanyController::forceDestroy()) clears those records first
     * by erasing the whole company, which is the only way such a doctor can
     * ever be permanently removed.
     */
    public function forceDestroy(User $user)
    {
        $company = Company::withTrashed()->find($user->company_id);

        if (! $user->trashed()) {
            return redirect()->route('admin.companies.show', $company)->with('error', 'Only an already-deleted user can be permanently deleted.');
        }

        try {
            $user->forceDelete();
        } catch (QueryException $e) {
            return redirect()->route('admin.companies.show', $company)->with('error', 'User could not be permanently deleted -- they still have appointments, visits, or other clinical records on file.');
        }

        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User permanently deleted.');
    }

    public function toggleStatus(User $user)
    {
        $company = $user->company;
        $newStatus = ($user->status->value ?? $user->status) === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active' && $company) {
            $this->companyUserLimit->assertCanHaveAnotherActiveUser($company, $user, (bool) $user->is_doctor);
        }

        $user->update(['status' => $newStatus]);

        if ($company) {
            $this->companyUserLimit->syncActiveUsers($company);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'User status updated successfully.');
    }
}
