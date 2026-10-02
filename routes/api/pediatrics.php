<?php

use App\Http\Controllers\Api\Pediatrics\AiConversationController;
use App\Http\Controllers\Api\Pediatrics\AppointmentController;
use App\Http\Controllers\Api\Pediatrics\ClientController;
use App\Http\Controllers\Api\Pediatrics\ClientProfileController;
use App\Http\Controllers\Api\Pediatrics\DashboardController;
use App\Http\Controllers\Api\Pediatrics\GrowthMeasurementController;
use App\Http\Controllers\Api\Pediatrics\VaccinationController;
use Illuminate\Support\Facades\Route;

Route::prefix('pediatrics')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('pediatrics.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('pediatrics.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('pediatrics.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('pediatrics.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('pediatrics.clients.profile.update');
    Route::get('clients/{client}/growth-measurements', [GrowthMeasurementController::class, 'index'])->name('pediatrics.growth-measurements.index');
    Route::post('clients/{client}/growth-measurements', [GrowthMeasurementController::class, 'store'])->name('pediatrics.growth-measurements.store');
    Route::put('growth-measurements/{record:uuid}', [GrowthMeasurementController::class, 'update'])->name('pediatrics.growth-measurements.update');
    Route::delete('growth-measurements/{record:uuid}', [GrowthMeasurementController::class, 'destroy'])->name('pediatrics.growth-measurements.destroy');
    Route::get('clients/{client}/vaccinations', [VaccinationController::class, 'index'])->name('pediatrics.vaccinations.index');
    Route::post('clients/{client}/vaccinations', [VaccinationController::class, 'store'])->name('pediatrics.vaccinations.store');
    Route::put('vaccinations/{record:uuid}', [VaccinationController::class, 'update'])->name('pediatrics.vaccinations.update');
    Route::delete('vaccinations/{record:uuid}', [VaccinationController::class, 'destroy'])->name('pediatrics.vaccinations.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('pediatrics.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('pediatrics.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('pediatrics.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('pediatrics.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('pediatrics.ai.confirm');
    });
});
