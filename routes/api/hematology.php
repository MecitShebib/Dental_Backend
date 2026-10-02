<?php

use App\Http\Controllers\Api\Hematology\AiConversationController;
use App\Http\Controllers\Api\Hematology\AppointmentController;
use App\Http\Controllers\Api\Hematology\BloodCountController;
use App\Http\Controllers\Api\Hematology\ClientController;
use App\Http\Controllers\Api\Hematology\ClientProfileController;
use App\Http\Controllers\Api\Hematology\DashboardController;
use App\Http\Controllers\Api\Hematology\TransfusionController;
use Illuminate\Support\Facades\Route;

Route::prefix('hematology')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('hematology.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('hematology.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('hematology.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('hematology.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('hematology.clients.profile.update');
    Route::get('clients/{client}/blood-counts', [BloodCountController::class, 'index'])->name('hematology.blood-counts.index');
    Route::post('clients/{client}/blood-counts', [BloodCountController::class, 'store'])->name('hematology.blood-counts.store');
    Route::put('blood-counts/{record:uuid}', [BloodCountController::class, 'update'])->name('hematology.blood-counts.update');
    Route::delete('blood-counts/{record:uuid}', [BloodCountController::class, 'destroy'])->name('hematology.blood-counts.destroy');
    Route::get('clients/{client}/transfusions', [TransfusionController::class, 'index'])->name('hematology.transfusions.index');
    Route::post('clients/{client}/transfusions', [TransfusionController::class, 'store'])->name('hematology.transfusions.store');
    Route::put('transfusions/{record:uuid}', [TransfusionController::class, 'update'])->name('hematology.transfusions.update');
    Route::delete('transfusions/{record:uuid}', [TransfusionController::class, 'destroy'])->name('hematology.transfusions.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('hematology.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('hematology.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('hematology.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('hematology.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('hematology.ai.confirm');
    });
});
