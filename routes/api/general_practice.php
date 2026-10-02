<?php

use App\Http\Controllers\Api\GeneralPractice\AiConversationController;
use App\Http\Controllers\Api\GeneralPractice\AppointmentController;
use App\Http\Controllers\Api\GeneralPractice\ClientController;
use App\Http\Controllers\Api\GeneralPractice\ClientProfileController;
use App\Http\Controllers\Api\GeneralPractice\DashboardController;
use App\Http\Controllers\Api\GeneralPractice\ReferralController;
use App\Http\Controllers\Api\GeneralPractice\VitalController;
use Illuminate\Support\Facades\Route;

Route::prefix('general_practice')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('general_practice.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('general_practice.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('general_practice.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('general_practice.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('general_practice.clients.profile.update');
    Route::get('clients/{client}/vitals', [VitalController::class, 'index'])->name('general_practice.vitals.index');
    Route::post('clients/{client}/vitals', [VitalController::class, 'store'])->name('general_practice.vitals.store');
    Route::put('vitals/{record:uuid}', [VitalController::class, 'update'])->name('general_practice.vitals.update');
    Route::delete('vitals/{record:uuid}', [VitalController::class, 'destroy'])->name('general_practice.vitals.destroy');
    Route::get('clients/{client}/referrals', [ReferralController::class, 'index'])->name('general_practice.referrals.index');
    Route::post('clients/{client}/referrals', [ReferralController::class, 'store'])->name('general_practice.referrals.store');
    Route::put('referrals/{record:uuid}', [ReferralController::class, 'update'])->name('general_practice.referrals.update');
    Route::delete('referrals/{record:uuid}', [ReferralController::class, 'destroy'])->name('general_practice.referrals.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('general_practice.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('general_practice.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('general_practice.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('general_practice.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('general_practice.ai.confirm');
    });
});
