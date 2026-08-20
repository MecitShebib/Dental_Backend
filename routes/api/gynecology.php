<?php

use App\Http\Controllers\Api\Gynecology\AiConversationController;
use App\Http\Controllers\Api\Gynecology\AppointmentController;
use App\Http\Controllers\Api\Gynecology\ClientController;
use App\Http\Controllers\Api\Gynecology\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('gynecology')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('gynecology.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('gynecology.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('gynecology.dashboard.stats');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('gynecology.ai.history');
    Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('gynecology.ai.send-message');
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('gynecology.ai.transcribe');
    Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('gynecology.ai.generate');
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('gynecology.ai.confirm');
});
