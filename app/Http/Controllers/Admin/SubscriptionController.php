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
use App\Services\SystemMessageService;
use App\Specialties\SpecialtyModuleRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        protected CompanyUserLimitService $companyUserLimit,
        protected SpecialtyModuleRegistry $specialtyModules,
        protected SystemMessageService $systemMessages,
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

    /**
     * The "System Messages" WhatsApp group (appointment reminder/patient
     * recall/booking confirmation/satisfaction survey, all 3 languages) --
     * same "only place a company newly gains a specialty" trigger as
     * seedSpecialtyCatalog() above. See SystemMessageService.
     */
    protected function seedSystemMessages(Company $company, int $specialtyId): void
    {
        $specialty = Specialty::find($specialtyId);
        if ($specialty) {
            $this->systemMessages->seedForCompanySpecialty($company, $specialty);
        }
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

        $this->assertSeatCapsCoverCurrentUsage($request, $company);

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
            $this->seedSystemMessages($company, $specialtyId);

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

        $this->assertSeatCapsCoverCurrentUsage($request, $company);

        if ($request->integer('max_branches') < $company->branches()->count()) {
            throw ValidationException::withMessages([
                'max_branches' => ['Max branches cannot be less than the company\'s current branch count.'],
            ])->errorBag($request->input('_modal_id') ?: 'default');
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
                $this->seedSystemMessages($company, $specialtyId);
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
            ])->errorBag($request->input('_modal_id') ?: 'default');
        }
    }

    public function destroy(Subscription $subscription)
    {
        // withTrashed(): see the identical note in Admin\UserController --
        // $subscription->company would resolve to null if the company is
        // already soft-deleted, and the redirect needs a real one.
        $company = Company::withTrashed()->find($subscription->company_id);
        $subscription->delete();

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription deleted successfully.');
    }

    /**
     * Independent of Admin\CompanyController::restore() (which restores
     * every subscription a company-delete cascaded into) -- this is for
     * restoring one specific subscription on its own.
     */
    public function restore(Subscription $subscription)
    {
        $company = Company::withTrashed()->find($subscription->company_id);
        $subscription->restore();

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription restored successfully.');
    }

    /**
     * The real, irreversible version of destroy() -- only reachable for an
     * already soft-deleted subscription (the view only shows this next to
     * Restore; the guard below protects the route itself). The only table
     * referencing a subscription row (ai_usage_logs.subscription_id) does so
     * with nullOnDelete, so this is a plain hard delete with no cascade
     * concerns -- the try/catch is just defensive symmetry with the
     * company/user versions.
     */
    public function forceDestroy(Subscription $subscription)
    {
        $company = Company::withTrashed()->find($subscription->company_id);

        if (! $subscription->trashed()) {
            return redirect()->route('admin.companies.show', $company)->with('error', 'Only an already-deleted subscription can be permanently deleted.');
        }

        try {
            $subscription->forceDelete();
        } catch (QueryException $e) {
            return redirect()->route('admin.companies.show', $company)->with('error', 'Subscription could not be permanently deleted.');
        }

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription permanently deleted.');
    }

    public function toggleStatus(Subscription $subscription)
    {
        $subscription->update([
            'status' => ($subscription->status->value ?? $subscription->status) === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->route('admin.companies.show', $subscription->company)->with('status', 'Subscription status updated successfully.');
    }

    /**
     * Per seat type: a cap can't be set below the
     * company's current active doctors / assistant (non-doctor) users, or
     * every later edit of an existing user would start failing validation.
     */
    protected function assertSeatCapsCoverCurrentUsage(Request $request, Company $company): void
    {
        $usage = $this->companyUserLimit->seatUsage($company);

        foreach (['max_doctors' => 'doctors', 'max_assistants' => 'assistants'] as $field => $key) {
            if ($request->filled($field) && $request->integer($field) < $usage[$key]['used']) {
                throw ValidationException::withMessages([
                    $field => ["Cannot be less than the company's current active {$key} count ({$usage[$key]['used']})."],
                ])->errorBag($request->input('_modal_id') ?: 'default');
            }
        }
    }
}
