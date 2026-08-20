<?php

use App\Http\Controllers\Api\Orthopedics\AiConversationController;
use App\Http\Controllers\Api\Orthopedics\AppointmentController;
use App\Http\Controllers\Api\Orthopedics\ClientController;
use App\Http\Controllers\Api\Orthopedics\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('orthopedics')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('orthopedics.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('orthopedics.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('orthopedics.dashboard.stats');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('orthopedics.ai.history');
    Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('orthopedics.ai.send-message');
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('orthopedics.ai.transcribe');
    Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('orthopedics.ai.generate');
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('orthopedics.ai.confirm');
});
