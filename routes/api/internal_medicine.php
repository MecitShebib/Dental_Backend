<?php

use App\Http\Controllers\Api\InternalMedicine\AiConversationController;
use App\Http\Controllers\Api\InternalMedicine\AppointmentController;
use App\Http\Controllers\Api\InternalMedicine\ClientController;
use App\Http\Controllers\Api\InternalMedicine\ClientProfileController;
use App\Http\Controllers\Api\InternalMedicine\DashboardController;
use App\Http\Controllers\Api\InternalMedicine\VitalController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal_medicine')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('internal_medicine.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('internal_medicine.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('internal_medicine.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('internal_medicine.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('internal_medicine.clients.profile.update');
    Route::get('clients/{client}/vitals', [VitalController::class, 'index'])->name('internal_medicine.vitals.index');
    Route::post('clients/{client}/vitals', [VitalController::class, 'store'])->name('internal_medicine.vitals.store');
    Route::put('vitals/{record:uuid}', [VitalController::class, 'update'])->name('internal_medicine.vitals.update');
    Route::delete('vitals/{record:uuid}', [VitalController::class, 'destroy'])->name('internal_medicine.vitals.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('internal_medicine.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('internal_medicine.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('internal_medicine.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('internal_medicine.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('internal_medicine.ai.confirm');
    });
});
