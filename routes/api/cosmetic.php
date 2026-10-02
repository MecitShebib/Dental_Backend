<?php

use App\Http\Controllers\Api\Cosmetic\AiConversationController;
use App\Http\Controllers\Api\Cosmetic\AppointmentController;
use App\Http\Controllers\Api\Cosmetic\ClientController;
use App\Http\Controllers\Api\Cosmetic\ClientProfileController;
use App\Http\Controllers\Api\Cosmetic\DashboardController;
use App\Http\Controllers\Api\Cosmetic\ProcedureLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('cosmetic')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('cosmetic.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('cosmetic.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('cosmetic.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('cosmetic.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('cosmetic.clients.profile.update');
    Route::get('clients/{client}/procedure-logs', [ProcedureLogController::class, 'index'])->name('cosmetic.procedure-logs.index');
    Route::post('clients/{client}/procedure-logs', [ProcedureLogController::class, 'store'])->name('cosmetic.procedure-logs.store');
    Route::put('procedure-logs/{record:uuid}', [ProcedureLogController::class, 'update'])->name('cosmetic.procedure-logs.update');
    Route::delete('procedure-logs/{record:uuid}', [ProcedureLogController::class, 'destroy'])->name('cosmetic.procedure-logs.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('cosmetic.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('cosmetic.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('cosmetic.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('cosmetic.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('cosmetic.ai.confirm');
    });
});
