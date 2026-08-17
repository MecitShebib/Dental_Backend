<?php

use App\Http\Controllers\Api\AiTreatmentPlanController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CallLogController;
use App\Http\Controllers\Api\CallLogWebhookController;
use App\Http\Controllers\Api\CallWebhookSettingsController;
use App\Http\Controllers\Api\CapitalTransactionController;
use App\Http\Controllers\Api\CariPartyController;
use App\Http\Controllers\Api\CariTransactionController;
use App\Http\Controllers\Api\ChronicCarePlanController;
use App\Http\Controllers\Api\ClientAppointmentController;
use App\Http\Controllers\Api\ClientCarePlanController;
use App\Http\Controllers\Api\ClientConsentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\ClientTreatmentRecordController;
use App\Http\Controllers\Api\ClientVisitController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyFundController;
use App\Http\Controllers\Api\CompanyTreatmentProductController;
use App\Http\Controllers\Api\ConsentTemplateController;
use App\Http\Controllers\Api\CosmeticCarePlanController;
use App\Http\Controllers\Api\CrmSettingsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorAvailabilityController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\EmployeeSalaryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryPurchaseOrderController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LabCaseController;
use App\Http\Controllers\Api\LabPartnerController;
use App\Http\Controllers\Api\LabPaymentController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\PatientRecallController;
use App\Http\Controllers\Api\PrenatalCarePlanController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\RehabCarePlanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalaryAdvanceController;
use App\Http\Controllers\Api\SalaryPaymentController;
use App\Http\Controllers\Api\SatisfactionSurveyController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\TreatmentCatalogInventoryLinkController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppSettingsController;
use App\Http\Controllers\Api\XrayImageController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/companies/{company:booking_slug}')->group(function () {
    Route::middleware('throttle:public-booking-read')->group(function () {
        Route::get('doctors', [PublicBookingController::class, 'doctors']);
        Route::get('availability', [PublicBookingController::class, 'availability']);
    });

    Route::middleware('throttle:public-booking-write')->group(function () {
        Route::post('book', [PublicBookingController::class, 'book']);
    });

    Route::middleware('throttle:call-webhook')->group(function () {
        Route::post('calls/webhook', CallLogWebhookController::class);
    });
});

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
    Route::get('specialties', [SpecialtyController::class, 'index']);
    Route::get('doctors', [UserController::class, 'doctors']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::get('companies/{company}/subscriptions', [CompanyController::class, 'subscriptions']);
    Route::get('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'index']);
    Route::post('companies/{company}/treatment-products', [CompanyTreatmentProductController::class, 'store']);
    Route::post('companies/{company}/treatment-products/bulk-price-adjustment', [CompanyTreatmentProductController::class, 'bulkPriceAdjustment']);
    Route::put('companies/{company}/treatment-products/{product}', [CompanyTreatmentProductController::class, 'update']);
    Route::delete('companies/{company}/treatment-products/{product}', [CompanyTreatmentProductController::class, 'destroy']);
    Route::get('companies/{company}/odontogram-treatment-prices', [CompanyTreatmentProductController::class, 'odontogramPrices']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('clients', ClientController::class);

    Route::get('clients/{client}/treatment-record', [ClientTreatmentRecordController::class, 'show']);
    Route::put('clients/{client}/treatment-record', [ClientTreatmentRecordController::class, 'update']);

    Route::get('clients/{client}/care-plans', [ClientCarePlanController::class, 'index']);
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
    Route::get('clients/{client}/consents', [ClientConsentController::class, 'index']);
    Route::post('clients/{client}/consents', [ClientConsentController::class, 'store']);
    Route::get('consent-templates', [ConsentTemplateController::class, 'index']);
    Route::post('consent-templates', [ConsentTemplateController::class, 'store']);
    Route::put('consent-templates/{template}', [ConsentTemplateController::class, 'update']);
    Route::delete('consent-templates/{template}', [ConsentTemplateController::class, 'destroy']);
    Route::get('clients/{client}/ai-conversation', [AiTreatmentPlanController::class, 'conversationHistory']);
    Route::post('clients/{client}/ai-conversation/messages', [AiTreatmentPlanController::class, 'sendMessage']);
    Route::post('clients/{client}/ai-treatment-plan/transcribe', [AiTreatmentPlanController::class, 'transcribe']);
    Route::post('clients/{client}/ai-treatment-plan/generate', [AiTreatmentPlanController::class, 'generatePlan']);
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiTreatmentPlanController::class, 'confirm']);
    Route::post('clients/{client}/ai-treatment-plan/charge', [AiTreatmentPlanController::class, 'addCharge']);

    // Gynevaria prototype -- see PrenatalCarePlanService's docblock.
    Route::post('clients/{client}/prenatal-care-plan/confirm', [PrenatalCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/chronic-care-plan/confirm', [ChronicCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/rehab-care-plan/confirm', [RehabCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/cosmetic-care-plan/confirm', [CosmeticCarePlanController::class, 'confirm']);

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

    Route::get('lab-cases/{labCase}/payments', [LabPaymentController::class, 'index']);
    Route::post('lab-cases/{labCase}/payments', [LabPaymentController::class, 'store']);
    Route::delete('lab-payments/{labPayment}', [LabPaymentController::class, 'destroy']);

    Route::get('cari/parties', [CariPartyController::class, 'index']);
    Route::post('cari/parties', [CariPartyController::class, 'store']);
    Route::put('cari/parties/{cariParty}', [CariPartyController::class, 'update']);
    Route::delete('cari/parties/{cariParty}', [CariPartyController::class, 'destroy']);
    Route::get('cari/parties/{cariParty}/summary', [CariPartyController::class, 'summary']);

    Route::get('cari/transactions', [CariTransactionController::class, 'index']);
    Route::get('cari/transactions/summary', [CariTransactionController::class, 'summary']);
    Route::post('cari/transactions', [CariTransactionController::class, 'store']);
    Route::put('cari/transactions/{cariTransaction}', [CariTransactionController::class, 'update']);
    Route::delete('cari/transactions/{cariTransaction}', [CariTransactionController::class, 'destroy']);

    Route::get('reports/patient-debts', [ReportController::class, 'patientDebts']);
    Route::get('reports/lab-debts', [ReportController::class, 'labDebts']);

    Route::get('reports/patient-recalls', [PatientRecallController::class, 'index']);
    Route::get('reports/payroll-summary', [ReportController::class, 'payrollSummary']);

    Route::get('satisfaction-surveys', [SatisfactionSurveyController::class, 'index']);
    Route::get('satisfaction-surveys/summary', [SatisfactionSurveyController::class, 'summary']);

    Route::get('call-logs', [CallLogController::class, 'index']);
    Route::post('call-logs', [CallLogController::class, 'store']);
    Route::delete('call-logs/{callLog}', [CallLogController::class, 'destroy']);
    Route::post('call-logs/{callLog}/follow-up', [CallLogController::class, 'markFollowedUp']);
    Route::get('call-logs/summary', [CallLogController::class, 'summary']);
    Route::post('clients/{client}/send-recall', [PatientRecallController::class, 'send']);
    Route::put('companies/{company}/recall-settings', [CompanyController::class, 'updateRecallSettings']);

    Route::get('settings/whatsapp', [WhatsAppSettingsController::class, 'show']);
    Route::put('settings/whatsapp', [WhatsAppSettingsController::class, 'update']);
    Route::delete('settings/whatsapp', [WhatsAppSettingsController::class, 'destroy']);
    Route::post('settings/whatsapp/test', [WhatsAppSettingsController::class, 'test']);

    Route::get('settings/call-webhook', [CallWebhookSettingsController::class, 'show']);
    Route::post('settings/call-webhook/regenerate', [CallWebhookSettingsController::class, 'regenerate']);

    Route::get('settings/message-templates', [MessageTemplateController::class, 'index']);
    Route::put('settings/message-templates', [MessageTemplateController::class, 'update']);

    Route::get('settings/crm', [CrmSettingsController::class, 'show']);
    Route::put('settings/crm', [CrmSettingsController::class, 'update']);
    Route::delete('settings/crm', [CrmSettingsController::class, 'destroy']);
    Route::post('settings/crm/test', [CrmSettingsController::class, 'test']);

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index']);
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store']);
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy']);

    Route::get('inventory-items', [InventoryItemController::class, 'index']);
    Route::post('inventory-items', [InventoryItemController::class, 'store']);
    Route::put('inventory-items/{item}', [InventoryItemController::class, 'update']);
    Route::delete('inventory-items/{item}', [InventoryItemController::class, 'destroy']);
    Route::get('inventory-items/{item}/transactions', [InventoryItemController::class, 'transactions']);
    Route::post('inventory-items/{item}/transactions', [InventoryItemController::class, 'storeTransaction']);
    Route::post('inventory-items/{item}/purchase-orders', [InventoryPurchaseOrderController::class, 'store']);

    Route::get('inventory-purchase-orders', [InventoryPurchaseOrderController::class, 'index']);
    Route::put('inventory-purchase-orders/{purchaseOrder}/status', [InventoryPurchaseOrderController::class, 'updateStatus']);

    Route::get('treatment-catalog/{catalogEntry}/inventory-links', [TreatmentCatalogInventoryLinkController::class, 'index']);
    Route::put('treatment-catalog/{catalogEntry}/inventory-links', [TreatmentCatalogInventoryLinkController::class, 'update']);

    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::put('branches/{branch}', [BranchController::class, 'update']);
    Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
    Route::get('branches/{branch}/summary', [BranchController::class, 'summary']);

    Route::get('xray-images', [XrayImageController::class, 'index']);
    Route::post('xray-images', [XrayImageController::class, 'store']);
    Route::put('xray-images/{xrayImage}', [XrayImageController::class, 'update']);
    Route::delete('xray-images/{xrayImage}', [XrayImageController::class, 'destroy']);
});

require __DIR__.'/api/gynecology.php';
