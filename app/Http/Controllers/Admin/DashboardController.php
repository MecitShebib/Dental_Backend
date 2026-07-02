<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'usersCount' => User::count(),
            'activeUsersCount' => User::where('status', 'active')->count(),
            'subscriptionsCount' => Subscription::count(),
            'activeSubscriptionsCount' => Subscription::where('status', 'active')->count(),
        ]);
    }
}
