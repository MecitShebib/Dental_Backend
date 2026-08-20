<?php

use App\Http\Controllers\Api\Cosmetic\AiConversationController;
use App\Http\Controllers\Api\Cosmetic\AppointmentController;
use App\Http\Controllers\Api\Cosmetic\ClientController;
use App\Http\Controllers\Api\Cosmetic\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('cosmetic')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('cosmetic.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('cosmetic.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('cosmetic.dashboard.stats');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('cosmetic.ai.history');
    Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('cosmetic.ai.send-message');
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('cosmetic.ai.transcribe');
    Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('cosmetic.ai.generate');
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('cosmetic.ai.confirm');
});
