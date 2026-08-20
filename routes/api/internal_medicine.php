<?php

use App\Http\Controllers\Api\InternalMedicine\AiConversationController;
use App\Http\Controllers\Api\InternalMedicine\AppointmentController;
use App\Http\Controllers\Api\InternalMedicine\ClientController;
use App\Http\Controllers\Api\InternalMedicine\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal_medicine')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('internal_medicine.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('internal_medicine.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('internal_medicine.dashboard.stats');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('internal_medicine.ai.history');
    Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('internal_medicine.ai.send-message');
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('internal_medicine.ai.transcribe');
    Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('internal_medicine.ai.generate');
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('internal_medicine.ai.confirm');
});
