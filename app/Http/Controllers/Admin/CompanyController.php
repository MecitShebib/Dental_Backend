<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialty;
use App\Services\CompanyUserLimitService;
use App\Services\SystemMessageService;
use Database\Seeders\KvkkConsentTemplateSeeder;
use Database\Seeders\TreatmentCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit) {}

    public function index(Request $request)
    {
        // withTrashed(): a deleted company must stay listed (with a Deleted
        // badge, see the view) rather than disappear -- that's what makes
        // restoring it possible at all.
        $companies = Company::withTrashed()
            ->with(['users', 'currentSubscription'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->get();

        return view('admin.companies.index', [
            'companies' => $companies,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Request $request, Company $company)
    {
        $company->load([
            'users.roles',
            'subscriptions',
            'currentSubscription',
        ]);

        return view('admin.companies.show', [
            'company' => $company,
            // withTrashed(): a deleted company's users/subscriptions were
            // soft-deleted along with it (see destroy() below) -- opening
            // its page needs to show them (each with its own Restore
            // action), not "0 users" as if they'd vanished.
            'users' => $company->users()->withTrashed()->with(['roles', 'permissions'])->orderBy('name')->get(),
            'subscriptions' => $company->subscriptions()->withTrashed()->with('specialty')->latest()->get(),
            'roles' => Role::orderBy('name')->get(),
            'specialties' => Specialty::orderBy('sort_order')->get(),
            'permissions' => Permission::orderBy('id')->get(),
            'branches' => $company->branches()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create([
            ...$request->validated(),
            'booking_slug' => Company::generateBookingSlug($request->validated('name')),
        ]);

        (new TreatmentCatalogSeeder)->seedCompany($company);
        (new KvkkConsentTemplateSeeder)->seedCompany($company);

        // Every company needs at least one branch to assign staff/patients
        // to (see CompanyBranchLimitService) -- rather than making that a
        // manual follow-up step, seed a default one here.
        Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'status' => 'active',
        ]);

        return redirect()->route('admin.companies.index')->with('status', 'Company created successfully.');
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        if ($company->wasChanged('language')) {
            app(SystemMessageService::class)->retitleForCompany($company);
        }

        return redirect()->route('admin.companies.index')->with('status', 'Company updated successfully.');
    }

    public function toggleStatus(Company $company)
    {
        $company->update([
            'status' => $company->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('status', 'Company status updated successfully.');
    }

    /**
     * Soft-deletes the company and cascades to its users and subscriptions
     * (the two examples the feature was asked for, and in practice the only
     * two tables that gate access -- see restore() below for why nothing
     * deeper in the data model needs touching). Nothing here is a real SQL
     * DELETE: every row stays in the database, just excluded from normal
     * queries by Eloquent's soft-delete scope, and index()/show() above
     * explicitly opt back in via withTrashed() so the company keeps showing
     * up (as Deleted) instead of disappearing.
     */
    public function destroy(Company $company)
    {
        DB::transaction(function () use ($company) {
            $company->users()->each(fn ($user) => $user->delete());
            $company->subscriptions()->each(fn ($subscription) => $subscription->delete());
            $company->delete();
        });

        return redirect()->route('admin.companies.index')->with('status', 'Company deleted successfully.');
    }

    /**
     * Mirrors destroy(): restoring the company also restores whichever of
     * its users/subscriptions were only soft-deleted *because of* that
     * cascade. A user or subscription can also be restored on its own from
     * the company page (Admin\UserController::restore() /
     * Admin\SubscriptionController::restore()) for finer-grained control --
     * this is just the "undo the whole thing" path.
     */
    public function restore(Company $company)
    {
        DB::transaction(function () use ($company) {
            $company->restore();
            $company->users()->onlyTrashed()->each(fn ($user) => $user->restore());
            $company->subscriptions()->onlyTrashed()->each(fn ($subscription) => $subscription->restore());
        });

        $this->companyUserLimit->syncActiveUsers($company);

        return redirect()->route('admin.companies.show', $company)->with('status', 'Company restored successfully.');
    }

    /**
     * The real, irreversible version of destroy() -- only reachable for a
     * company that's already soft-deleted (the view only shows this next to
     * Restore; the guard below protects the route itself from a direct
     * call). A hard DELETE on the company row cascades at the database level
     * through every company_id foreign key in the schema (clients,
     * appointments, visits, payments, invoices, inventory, branches,
     * subscriptions, ...), not just users/subscriptions -- there's no way to
     * hard-delete a company without also erasing everything scoped to it.
     * Users still need deleting explicitly afterward since users.company_id
     * is nullOnDelete, not cascade -- and must come AFTER the company itself
     * (not before), since appointments/prescriptions/lab records restrict-
     * delete their doctor_id and only the company's own cascade clears those
     * out first. withoutEvents(): the Auditable trait (see
     * app/Models/Concerns/Auditable.php) writes a fresh AuditLog row on every
     * model deletion, including this one -- but by the time these users are
     * force-deleted the company row is already gone, so AuditLog::record()'s
     * company_id fallback would try to insert a row referencing a company
     * that no longer exists and fail its own foreign key. There's nothing
     * worth auditing here anyway: the whole point of an audit trail is
     * tracing activity on a company's data, and that company is being
     * erased in this same request. Wrapped in a transaction + caught: a
     * handful of tables restrict-delete their doctor_id (appointments,
     * prescriptions, ...), which would otherwise surface as a raw 500 for
     * the rare row that escaped the company_id cascade (e.g. a legacy row
     * with a null company_id).
     */
    public function forceDestroy(Company $company)
    {
        if (! $company->trashed()) {
            return redirect()->route('admin.companies.index')->with('error', 'Only an already-deleted company can be permanently deleted.');
        }

        $users = $company->users()->withTrashed()->get();

        try {
            DB::transaction(function () use ($company, $users) {
                Company::withoutEvents(function () use ($company, $users) {
                    $company->forceDelete();
                    $users->each(fn ($user) => $user->forceDelete());
                });
            });
        } catch (QueryException $e) {
            return redirect()->route('admin.companies.index')->with('error', 'Company could not be permanently deleted -- other records still reference it.');
        }

        return redirect()->route('admin.companies.index')->with('status', 'Company permanently deleted.');
    }
}
