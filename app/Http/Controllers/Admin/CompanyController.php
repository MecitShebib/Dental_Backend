<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
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
            'users' => $company->users()->with('roles')->orderBy('name')->get(),
            'subscriptions' => $company->subscriptions()->latest()->get(),
            'roles' => \App\Models\Role::orderBy('name')->get(),
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        Company::create($request->validated());

        return redirect()->route('admin.companies.index')->with('status', 'Company created successfully.');
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return redirect()->route('admin.companies.index')->with('status', 'Company updated successfully.');
    }

    public function toggleStatus(Company $company)
    {
        $company->update([
            'status' => $company->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('status', 'Company status updated successfully.');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('admin.companies.index')->with('status', 'Company deleted successfully.');
    }
}
