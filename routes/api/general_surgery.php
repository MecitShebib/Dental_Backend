<?php

use App\Http\Controllers\Api\GeneralSurgery\AiConversationController;
use App\Http\Controllers\Api\GeneralSurgery\AppointmentController;
use App\Http\Controllers\Api\GeneralSurgery\ClientController;
use App\Http\Controllers\Api\GeneralSurgery\ClientProfileController;
use App\Http\Controllers\Api\GeneralSurgery\DashboardController;
use App\Http\Controllers\Api\GeneralSurgery\FollowupController;
use App\Http\Controllers\Api\GeneralSurgery\OperationController;
use Illuminate\Support\Facades\Route;

Route::prefix('general_surgery')->middleware(['auth:sanctum', 'active.clinic', 'terms.accepted'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('general_surgery.clients');
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->names('general_surgery.appointments');
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('general_surgery.dashboard.stats');

    // Clinical profile + repeating records (2026-09-27, spec Aşama 3).
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('general_surgery.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('general_surgery.clients.profile.update');
    Route::get('clients/{client}/operations', [OperationController::class, 'index'])->name('general_surgery.operations.index');
    Route::post('clients/{client}/operations', [OperationController::class, 'store'])->name('general_surgery.operations.store');
    Route::put('operations/{record:uuid}', [OperationController::class, 'update'])->name('general_surgery.operations.update');
    Route::delete('operations/{record:uuid}', [OperationController::class, 'destroy'])->name('general_surgery.operations.destroy');
    Route::get('clients/{client}/followups', [FollowupController::class, 'index'])->name('general_surgery.followups.index');
    Route::post('clients/{client}/followups', [FollowupController::class, 'store'])->name('general_surgery.followups.store');
    Route::put('followups/{record:uuid}', [FollowupController::class, 'update'])->name('general_surgery.followups.update');
    Route::delete('followups/{record:uuid}', [FollowupController::class, 'destroy'])->name('general_surgery.followups.destroy');

    Route::get('clients/{client}/ai-conversation', [AiConversationController::class, 'conversationHistory'])->name('general_surgery.ai.history');
    Route::middleware('kvkk.consent')->group(function () {
        Route::post('clients/{client}/ai-conversation/messages', [AiConversationController::class, 'sendMessage'])->name('general_surgery.ai.send-message');
        Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiConversationController::class, 'transcribe'])->name('general_surgery.ai.transcribe');
        Route::post('clients/{client}/ai-treatment-plan/generate', [AiConversationController::class, 'generatePlan'])->name('general_surgery.ai.generate');
        Route::post('clients/{client}/ai-treatment-plan/confirm', [AiConversationController::class, 'confirm'])->name('general_surgery.ai.confirm');
    });
});
