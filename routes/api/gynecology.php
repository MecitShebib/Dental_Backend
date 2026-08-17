<?php

use App\Http\Controllers\Api\Gynecology\AppointmentController;
use App\Http\Controllers\Api\Gynecology\ClientController;
use App\Http\Controllers\Api\Gynecology\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('gynecology')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});
