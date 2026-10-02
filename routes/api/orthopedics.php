<?php

use App\Http\Controllers\Api\Orthopedics\AiConversationController;
use App\Http\Controllers\Api\Orthopedics\AppointmentController;
use App\Http\Controllers\Api\Orthopedics\AssessmentController;
use App\Http\Controllers\Api\Orthopedics\ClientController;
use App\Http\Controllers\Api\Orthopedics\ClientProfileController;
use App\Http\Controllers\Api\Orthopedics\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('orthopedics')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('orthopedics.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('orthopedics.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('orthopedics.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('orthopedics.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('orthopedics.clients.profile.update');
    Route::get('clients/{client}/assessments', [AssessmentController::class, 'index'])->name('orthopedics.assessments.index');
    Route::post('clients/{client}/assessments', [AssessmentController::class, 'store'])->name('orthopedics.assessments.store');
    Route::put('assessments/{record:uuid}', [AssessmentController::class, 'update'])->name('orthopedics.assessments.update');
    Route::delete('assessments/{record:uuid}', [AssessmentController::class, 'destroy'])->name('orthopedics.assessments.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('orthopedics.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('orthopedics.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('orthopedics.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('orthopedics.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('orthopedics.ai.confirm');
    });
});
