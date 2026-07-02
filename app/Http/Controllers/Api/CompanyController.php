<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;

class CompanyController extends Controller
{
    public function show(Company $company)
    {
        $company->load('currentSubscription')
            ->loadCount('users')
            ->setAttribute('active_users_count', $company->users()->where('status', 'active')->count());

        return $this->success(CompanyResource::make($company));
    }

    public function subscriptions(Company $company)
    {
        $subscriptions = $company->subscriptions()
            ->latest('starts_at')
            ->latest('id')
            ->get();

        return $this->success(SubscriptionResource::collection($subscriptions));
    }
}
