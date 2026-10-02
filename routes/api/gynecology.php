<?php

use App\Http\Controllers\Api\Gynecology\AiConversationController;
use App\Http\Controllers\Api\Gynecology\AppointmentController;
use App\Http\Controllers\Api\Gynecology\ClientController;
use App\Http\Controllers\Api\Gynecology\ClientProfileController;
use App\Http\Controllers\Api\Gynecology\DashboardController;
use App\Http\Controllers\Api\Gynecology\UltrasoundExamController;
use Illuminate\Support\Facades\Route;

Route::prefix('gynecology')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('gynecology.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('gynecology.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('gynecology.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('gynecology.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('gynecology.clients.profile.update');
    Route::get('clients/{client}/ultrasound-exams', [UltrasoundExamController::class, 'index'])->name('gynecology.ultrasound-exams.index');
    Route::post('clients/{client}/ultrasound-exams', [UltrasoundExamController::class, 'store'])->name('gynecology.ultrasound-exams.store');
    Route::put('ultrasound-exams/{record:uuid}', [UltrasoundExamController::class, 'update'])->name('gynecology.ultrasound-exams.update');
    Route::delete('ultrasound-exams/{record:uuid}', [UltrasoundExamController::class, 'destroy'])->name('gynecology.ultrasound-exams.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('gynecology.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('gynecology.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('gynecology.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('gynecology.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('gynecology.ai.confirm');
    });
});
