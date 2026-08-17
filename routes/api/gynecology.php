<?php

use App\Http\Controllers\Api\Gynecology\AppointmentController;
use App\Http\Controllers\Api\Gynecology\ClientController;
use App\Http\Controllers\Api\Gynecology\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('gynecology')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('gynecology.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('gynecology.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('gynecology.dashboard.stats');
});
