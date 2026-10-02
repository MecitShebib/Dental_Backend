<?php

use App\Http\Controllers\Api\Physiotherapy\AiConversationController;
use App\Http\Controllers\Api\Physiotherapy\AppointmentController;
use App\Http\Controllers\Api\Physiotherapy\ClientController;
use App\Http\Controllers\Api\Physiotherapy\ClientProfileController;
use App\Http\Controllers\Api\Physiotherapy\DashboardController;
use App\Http\Controllers\Api\Physiotherapy\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('physiotherapy')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('physiotherapy.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('physiotherapy.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('physiotherapy.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('physiotherapy.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('physiotherapy.clients.profile.update');
    Route::get('clients/{client}/sessions', [SessionController::class, 'index'])->name('physiotherapy.sessions.index');
    Route::post('clients/{client}/sessions', [SessionController::class, 'store'])->name('physiotherapy.sessions.store');
    Route::put('sessions/{record:uuid}', [SessionController::class, 'update'])->name('physiotherapy.sessions.update');
    Route::delete('sessions/{record:uuid}', [SessionController::class, 'destroy'])->name('physiotherapy.sessions.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('physiotherapy.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('physiotherapy.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('physiotherapy.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('physiotherapy.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('physiotherapy.ai.confirm');
    });
});
