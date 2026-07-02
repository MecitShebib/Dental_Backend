<?php

use App\Http\Controllers\Api\AiTreatmentPlanController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientAppointmentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\ClientTreatmentRecordController;
use App\Http\Controllers\Api\ClientVisitController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyTreatmentProductController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorAvailabilityController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('doctors', [UserController::class, 'doctors']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::get('companies/{company}/subscriptions', [CompanyController::class, 'subscriptions']);
    Route::get('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'index']);
    Route::post('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'store']);
    Route::put('companies/{company}/treatment-products/{product}', [CompanyTreatmentProductController::class, 'update']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('clients', ClientController::class);

    Route::get('clients/{client}/treatment-record', [ClientTreatmentRecordController::class, 'show']);
    Route::put('clients/{client}/treatment-record', [ClientTreatmentRecordController::class, 'update']);

    Route::get('clients/{client}/visits', [ClientVisitController::class, 'index']);
    Route::post('clients/{client}/visits', [ClientVisitController::class, 'store']);
    Route::put('visits/{visit}', [ClientVisitController::class, 'update']);
    Route::delete('visits/{visit}', [ClientVisitController::class, 'destroy']);

    Route::get('clients/{client}/payments', [ClientPaymentController::class, 'index']);
    Route::post('clients/{client}/payments', [ClientPaymentController::class, 'store']);
    Route::put('payments/{payment}', [ClientPaymentController::class, 'update']);
    Route::delete('payments/{payment}', [ClientPaymentController::class, 'destroy']);

    Route::get('clients/{client}/appointments', [ClientAppointmentController::class, 'index']);
    Route::post('clients/{client}/ai-treatment-plan', [AiTreatmentPlanController::class, 'preview']);
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiTreatmentPlanController::class, 'confirm']);

    Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'show']);
    Route::put('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'update']);

    Route::get('doctors/{doctor}/availability', [DoctorAvailabilityController::class, 'availability']);
    Route::get('doctors/{doctor}/available-start-times', [DoctorAvailabilityController::class, 'availableStartTimes']);
    Route::get('doctors/{doctor}/available-durations', [DoctorAvailabilityController::class, 'availableDurations']);

    Route::apiResource('appointments', AppointmentController::class);
    Route::post('appointments/{appointment}/check-in', [ClientVisitController::class, 'checkIn']);
    Route::post('appointments/{appointment}/no-show', [ClientVisitController::class, 'noShow']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});
