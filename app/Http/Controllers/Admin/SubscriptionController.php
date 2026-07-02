<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Models\Company;
use App\Models\Subscription;
use App\Services\CompanyUserLimitService;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(protected CompanyUserLimitService $companyUserLimit)
    {
    }

    public function index()
    {
        return view('admin.subscriptions.index', [
            'subscriptions' => Subscription::with('company')->latest()->get(),
            'companies' => Company::orderBy('name')->get(),
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

        $subscription = Subscription::create([
            ...$request->validated(),
            'active_users' => $activeUsers,
        ]);
        $this->companyUserLimit->syncSubscription($subscription);

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription created successfully.');
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

        $subscription->update($request->validated());
        $this->companyUserLimit->syncSubscription($subscription->fresh('company'));

        return redirect()->route('admin.companies.show', $company)->with('status', 'Subscription updated successfully.');
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
