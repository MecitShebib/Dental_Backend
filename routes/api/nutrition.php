<?php

use App\Http\Controllers\Api\Nutrition\AiConversationController;
use App\Http\Controllers\Api\Nutrition\AppointmentController;
use App\Http\Controllers\Api\Nutrition\BodyMetricController;
use App\Http\Controllers\Api\Nutrition\ClientController;
use App\Http\Controllers\Api\Nutrition\ClientProfileController;
use App\Http\Controllers\Api\Nutrition\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('nutrition')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('nutrition.clients');
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('nutrition.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('nutrition.clients.profile.update');
    Route::get('clients/{client}/body-metrics', [BodyMetricController::class, 'index'])->name('nutrition.body-metrics.index');
    Route::post('clients/{client}/body-metrics', [BodyMetricController::class, 'store'])->name('nutrition.body-metrics.store');
    Route::delete('body-metrics/{bodyMetric:uuid}', [BodyMetricController::class, 'destroy'])->name('nutrition.body-metrics.destroy');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('nutrition.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('nutrition.dashboard.stats');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('nutrition.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('nutrition.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('nutrition.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('nutrition.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('nutrition.ai.confirm');
    });
});
