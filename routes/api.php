<?php

use App\Http\Controllers\Api\AiTreatmentPlanController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CapitalTransactionController;
use App\Http\Controllers\Api\ClientAppointmentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\ClientTreatmentRecordController;
use App\Http\Controllers\Api\ClientVisitController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyFundController;
use App\Http\Controllers\Api\CompanyTreatmentProductController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorAvailabilityController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\EmployeeSalaryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LabCaseController;
use App\Http\Controllers\Api\LabPartnerController;
use App\Http\Controllers\Api\SalaryAdvanceController;
use App\Http\Controllers\Api\SalaryPaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\XrayImageController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:otp-request')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    });

    Route::middleware('throttle:otp-verify')->group(function () {
        Route::post('login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
        Route::post('forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOtp']);
    });

    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'active.clinic'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::get('doctors', [UserController::class, 'doctors']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::get('companies/{company}/subscriptions', [CompanyController::class, 'subscriptions']);
    Route::get('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'index']);
    Route::post('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'store']);
    Route::put('companies/{company}/treatment-products/{product}', [CompanyTreatmentProductController::class, 'update']);
    Route::delete('companies/{company}/treatment-products/{product}', [CompanyTreatmentProductController::class, 'destroy']);
    Route::get('companies/{company}/odontogram-treatment-prices', [CompanyTreatmentProductController::class, 'odontogramPrices']);
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

    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);

    Route::get('clients/{client}/appointments', [ClientAppointmentController::class, 'index']);
    Route::post('clients/{client}/ai-treatment-plan', [AiTreatmentPlanController::class, 'preview']);
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiTreatmentPlanController::class, 'transcribe']);
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiTreatmentPlanController::class, 'confirm']);
    Route::post('clients/{client}/ai-treatment-plan/charge', [AiTreatmentPlanController::class, 'addCharge']);

    Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'show']);
    Route::put('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'update']);

    Route::get('doctors/{doctor}/availability', [DoctorAvailabilityController::class, 'availability']);
    Route::get('doctors/{doctor}/available-start-times', [DoctorAvailabilityController::class, 'availableStartTimes']);
    Route::get('doctors/{doctor}/available-durations', [DoctorAvailabilityController::class, 'availableDurations']);

    Route::apiResource('appointments', AppointmentController::class);
    Route::post('appointments/{appointment}/check-in', [ClientVisitController::class, 'checkIn']);
    Route::post('appointments/{appointment}/no-show', [ClientVisitController::class, 'noShow']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('fund/summary', [CompanyFundController::class, 'summary']);
    Route::get('fund/transactions', [CompanyFundController::class, 'index']);

    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::post('expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

    Route::apiResource('capital-transactions', CapitalTransactionController::class)->except(['show']);

    Route::get('payroll/employees', [EmployeeSalaryController::class, 'index']);
    Route::put('payroll/employees/{user}/salary', [EmployeeSalaryController::class, 'update']);

    Route::get('payroll/salary-advances', [SalaryAdvanceController::class, 'index']);
    Route::post('payroll/salary-advances', [SalaryAdvanceController::class, 'store']);
    Route::put('payroll/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'update']);
    Route::delete('payroll/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'destroy']);

    Route::get('payroll/salary-payments', [SalaryPaymentController::class, 'index']);
    Route::post('payroll/salary-payments', [SalaryPaymentController::class, 'store']);
    Route::get('payroll/salary-payments/{salaryPayment}', [SalaryPaymentController::class, 'show']);
    Route::put('payroll/salary-payments/{salaryPayment}', [SalaryPaymentController::class, 'update']);
    Route::delete('payroll/salary-payments/{salaryPayment}', [SalaryPaymentController::class, 'destroy']);

    Route::get('lab-partners', [LabPartnerController::class, 'index']);
    Route::post('lab-partners', [LabPartnerController::class, 'store']);
    Route::put('lab-partners/{labPartner}', [LabPartnerController::class, 'update']);
    Route::delete('lab-partners/{labPartner}', [LabPartnerController::class, 'destroy']);

    Route::get('lab-cases', [LabCaseController::class, 'all']);
    Route::get('clients/{client}/lab-cases', [LabCaseController::class, 'index']);
    Route::post('clients/{client}/lab-cases', [LabCaseController::class, 'store']);
    Route::put('lab-cases/{labCase}', [LabCaseController::class, 'update']);
    Route::delete('lab-cases/{labCase}', [LabCaseController::class, 'destroy']);

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index']);
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store']);
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy']);

    Route::get('xray-images', [XrayImageController::class, 'index']);
    Route::post('xray-images', [XrayImageController::class, 'store']);
    Route::put('xray-images/{xrayImage}', [XrayImageController::class, 'update']);
    Route::delete('xray-images/{xrayImage}', [XrayImageController::class, 'destroy']);
});
