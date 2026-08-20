<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Services\CompanyUserLimitService;
use App\Specialties\SpecialtyModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        protected CompanyUserLimitService $companyUserLimit,
        protected SpecialtyModuleRegistry $specialtyModules,
    ) {}

    /**
     * Seeds that specialty's treatment/procedure catalog for the company the
     * moment it actually subscribes -- the same "isn't left with an empty
     * catalog" reasoning TreatmentCatalogSeeder::seedCompany() documents for
     * dental at company-creation time, extended to the other 4 specialties
     * here at subscription time instead (this is the only place a company
     * newly gains a specialty). A no-op for a not-yet-built specialty
     * module and safe to call repeatedly (seedCatalog() is updateOrCreate-based).
     */
    protected function seedSpecialtyCatalog(Company $company, int $specialtyId): void
    {
        $key = Specialty::find($specialtyId)?->key;
        $module = $key ? $this->specialtyModules->get($key) : null;
        $module?->seedCatalog($company);
    }

    public function index()
    {
        return view('admin.subscriptions.index', [
            'subscriptions' => Subscription::with(['company', 'specialty'])->latest()->get(),
            'companies' => Company::orderBy('name')->get(),
            'specialties' => Specialty::orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $company = Company::findOrFail($request->validated('company_id'));
        $activeUsers = $company->users()->where('status', 'active')->count();

        if ($request->integer('max_users') < $activeUsers) {
            throw ValidationException::withMessages([
                'max_users' => ['Max users cannot be less than the company active users count.'],
            ]);
        }

        $specialtyIds = $request->validated('specialty_ids');
        foreach ($specialtyIds as $specialtyId) {
            $this->assertNoDuplicateActiveSpecialty($request, $company, null, $specialtyId);
        }

        $sharedFields = collect($request->validated())->except(['specialty_ids'])->all();

        $created = DB::transaction(fn () => collect($specialtyIds)->map(function ($specialtyId) use ($company, $sharedFields, $activeUsers) {
            $subscription = Subscription::create([
                ...$sharedFields,
                'specialty_id' => $specialtyId,
                'active_users' => $activeUsers,
            ]);
            $this->companyUserLimit->syncSubscription($subscription);
            $this->seedSpecialtyCatalog($company, $specialtyId);

            return $subscription;
        }));

        $status = $created->count() > 1
            ? $created->count().' subscriptions created successfully.'
            : 'Subscription created successfully.';

        return redirect()->route('admin.companies.show', $company)->with('status', $status);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription)
    {
        $company = Company::findOrFail($request->validated('company_id'));
        $activeUsers = $company->users()->where('status', 'active')->count();

        if ($request->integer('max_users') < $activeUsers) {
            throw ValidationException::withMessages([
                'max_users' => ['Max users cannot be less than the company active users count.'],
            ]);
        }

        if ($request->integer('max_branches') < $company->branches()->count()) {
            throw ValidationException::withMessages([
                'max_branches' => ['Max branches cannot be less than the company\'s current branch count.'],
            ]);
        }

        // Same "select several specialties at once" the create form
        // supports, added on top of an update. This row's OWN specialty
        // never changes via this checkbox set -- reading "which one is
        // primary" back from the submitted array would depend on checkbox
        // DOM order, not which one the admin actually meant, and could
        // silently reassign the wrong row's specialty. Any *other* checked
        // specialty becomes a new sibling row instead; there's no "unlink a
        // specialty" gesture here, that's what the existing per-row Delete
        // Subscription action is for.
        $specialtyIds = $request->validated('specialty_ids');
        $additionalSpecialtyIds = array_values(array_diff($specialtyIds, [$subscription->specialty_id]));

        $this->assertNoDuplicateActiveSpecialty($request, $company, $subscription, $subscription->specialty_id);
        foreach ($additionalSpecialtyIds as $specialtyId) {
            $this->assertNoDuplicateActiveSpecialty($request, $company, null, $specialtyId);
        }

        $sharedFields = collect($request->validated())->except(['specialty_ids', 'active_users'])->all();

        DB::transaction(function () use ($company, $subscription, $sharedFields, $additionalSpecialtyIds, $activeUsers) {
            // active_users is deliberately left out of this update -- the
            // admin form has no field for it, and syncSubscription() right
            // below recomputes + writes the correct value for every active
            // subscription anyway (see CompanyUserLimitService::syncActiveUsers).
            // Setting it to whatever a missing field validates to (null)
            // would violate the column's NOT NULL constraint.
            $subscription->update($sharedFields);
            $this->companyUserLimit->syncSubscription($subscription->fresh('company'));

            foreach ($additionalSpecialtyIds as $specialtyId) {
                $newSubscription = Subscription::create([
                    ...$sharedFields,
                    'specialty_id' => $specialtyId,
                    'active_users' => $activeUsers,
                ]);
                $this->companyUserLimit->syncSubscription($newSubscription);
                $this->seedSpecialtyCatalog($company, $specialtyId);
            }
        });

        $status = count($additionalSpecialtyIds) > 0
            ? 'Subscription updated and '.count($additionalSpecialtyIds).' new subscription(s) created.'
            : 'Subscription updated successfully.';

        return redirect()->route('admin.companies.show', $company)->with('status', $status);
    }

    /**
     * Phase 2's pooled company-wide limits (Company::aggregatedSubscriptionLimit)
     * assume at most one meaningful active subscription per specialty --
     * guard the admin form against accidentally creating a second one, which
     * would silently double-count that specialty's limits in the pool.
     */
    protected function assertNoDuplicateActiveSpecialty($request, Company $company, ?Subscription $ignoreSubscription = null, ?int $specialtyId = null): void
    {
        if ($request->validated('status') !== SubscriptionStatus::Active->value) {
            return;
        }

        $specialtyId ??= $request->validated('specialty_id');

        $duplicateExists = $company->subscriptions()
            ->where('specialty_id', $specialtyId)
            ->where('status', SubscriptionStatus::Active->value)
            ->when($ignoreSubscription, fn ($query) => $query->whereKeyNot($ignoreSubscription->id))
            ->exists();

        if ($duplicateExists) {
            $specialtyName = Specialty::find($specialtyId)?->brand_name ?? "#{$specialtyId}";

            throw ValidationException::withMessages([
                'specialty_ids' => ["This company already has an active subscription for {$specialtyName}."],
            ]);
        }
    }

    public function destroy(Subscription $subscription)
    {
        $company = $subscription->company;
        $subscription->delete();

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription deleted successfully.');
    }

    public function toggleStatus(Subscription $subscription)
    {
        $subscription->update([
            'status' => ($subscription->status->value ?? $subscription->status) === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->route('admin.companies.show', $subscription->company)->with('status', 'Subscription status updated successfully.');
    }
}
