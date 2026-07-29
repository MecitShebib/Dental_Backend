<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function show(Request $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        $company->load('currentSubscription')
            ->loadCount('users')
            ->setAttribute('active_users_count', $company->users()->where('status', 'active')->count());

        return $this->success(CompanyResource::make($company));
    }

    public function subscriptions(Request $request, Company $company)
    {
        $this->assertBelongsToRequester($request, $company);

        $subscriptions = $company->subscriptions()
            ->latest('starts_at')
            ->latest('id')
            ->get();

        return $this->success(SubscriptionResource::collection($subscriptions));
    }

    protected function assertBelongsToRequester(Request $request, Company $company): void
    {
        if ($request->user()->isProjectAdmin()) {
            return;
        }

        if ((int) $company->id !== (int) $request->user()->company_id) {
            throw ValidationException::withMessages([
                'company' => ['The selected company does not belong to your account.'],
            ]);
        }
    }
}
