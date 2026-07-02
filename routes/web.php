<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
        Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        Route::get('companies', [AdminCompanyController::class, 'index'])->name('admin.companies.index');
        Route::post('companies', [AdminCompanyController::class, 'store'])->name('admin.companies.store');
        Route::get('companies/{company}', [AdminCompanyController::class, 'show'])->name('admin.companies.show');
        Route::put('companies/{company}', [AdminCompanyController::class, 'update'])->name('admin.companies.update');
        Route::delete('companies/{company}', [AdminCompanyController::class, 'destroy'])->name('admin.companies.destroy');

        Route::post('users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle-status');

        Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])->name('admin.subscriptions.store');
        Route::put('subscriptions/{subscription}', [AdminSubscriptionController::class, 'update'])->name('admin.subscriptions.update');
        Route::delete('subscriptions/{subscription}', [AdminSubscriptionController::class, 'destroy'])->name('admin.subscriptions.destroy');
        Route::patch('subscriptions/{subscription}/toggle-status', [AdminSubscriptionController::class, 'toggleStatus'])->name('admin.subscriptions.toggle-status');
        Route::patch('companies/{company}/toggle-status', [AdminCompanyController::class, 'toggleStatus'])->name('admin.companies.toggle-status');
    });
});
