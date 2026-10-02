<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\LandingPageInquiryController as AdminLandingPageInquiryController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\SystemStatusController as AdminSystemStatusController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\LandingPageInquiryController;
use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\SatisfactionSurveyPublicController;
use App\Http\Controllers\SharedDocumentFileController;
use App\Models\Company;
use App\Models\LandingPageContent;
use App\Support\ApiDocumentation;
use App\Support\LegalContent;
use Illuminate\Support\Facades\Route;

// Per-specialty (not one shared page): each business unit only cares about
// its own slice of the API -- dental's X-Ray/DICOM/Lab groups meant nothing
// to a Gynevaria integrator reading the same unified page. Uses the
// specialty *key* (gynecology, internal_medicine, ...), same segment the
// app's own in-product routes use (see App.jsx's /internal_medicine/...
// routes) -- not LandingPageContent's marketing *slug* (gynevaria, ...),
// which is a different, brand-facing identifier for the public site only.
Route::get('/{specialty}/api-docs', function (string $specialty) {
    return view('api-docs', [
        'groups' => ApiDocumentation::groups($specialty),
        'enums' => ApiDocumentation::enums(),
        'baseUrl' => ApiDocumentation::baseUrl(),
        'specialty' => $specialty,
        'specialtySlug' => LandingPageContent::SPECIALTY_SLUGS[$specialty],
        'brandName' => ApiDocumentation::specialtyBrandName($specialty),
        'accent' => LandingPageContent::SPECIALTY_ACCENTS[$specialty],
    ]);
})->where('specialty', implode('|', LandingPageContent::SPECIALTIES))->name('api-docs');

// Old unified /api-docs -- keeps existing bookmarks/links alive by sending
// them to the flagship product's own docs instead of 404ing.
Route::redirect('/api-docs', '/dental/api-docs', 301);

// Pedivaria/Generavaria were renamed to Pediavaria/Genervaria on 2026-09-27,
// the same day they went live -- keep the already-published URLs working.
foreach (['pedivaria' => 'pediavaria', 'generavaria' => 'genervaria'] as $oldSlug => $newSlug) {
    Route::redirect("/{$oldSlug}", "/{$newSlug}", 301);
    foreach (['en', 'ar', 'tr'] as $redirectLocale) {
        Route::redirect("/{$redirectLocale}/{$oldSlug}", "/{$redirectLocale}/{$newSlug}", 301);
    }
    Route::redirect("/proposal-{$oldSlug}.html", "/proposal-{$newSlug}.html", 301);
    Route::redirect("/pitch-{$oldSlug}.html", "/pitch-{$newSlug}.html", 301);
}

Route::get('/privacy-policy', function () {
    return view('legal', ['page' => 'privacy', 'locale' => 'en', 'legal' => LegalContent::get('privacy', 'en')]);
})->name('privacy.default');

Route::get('/{locale}/privacy-policy', function (string $locale) {
    return view('legal', ['page' => 'privacy', 'locale' => $locale, 'legal' => LegalContent::get('privacy', $locale)]);
})->where('locale', 'en|ar|tr')->name('privacy');

Route::get('/terms-of-service', function () {
    return view('legal', ['page' => 'terms', 'locale' => 'en', 'legal' => LegalContent::get('terms', 'en')]);
})->name('terms.default');

Route::get('/{locale}/terms-of-service', function (string $locale) {
    return view('legal', ['page' => 'terms', 'locale' => $locale, 'legal' => LegalContent::get('terms', $locale)]);
})->where('locale', 'en|ar|tr')->name('terms');

// Public-disk files (message attachments, ...) when FILES_ROOT keeps them
// outside the web root -- see config/filesystems.php.
Route::get('/files/{path}', [PublicFileController::class, 'show'])
    ->where('path', '.*')
    ->middleware('throttle:120,1')
    ->name('public-files.show');

// Patient document PDFs shared over WhatsApp (random UUID = the secret).
Route::get('/d/{uuid}', [SharedDocumentFileController::class, 'show'])
    ->whereUuid('uuid')
    ->middleware('throttle:60,1')
    ->name('shared-documents.file');

Route::get('/survey/{token}', [SatisfactionSurveyPublicController::class, 'show'])->name('survey.show');
Route::post('/survey/{token}', [SatisfactionSurveyPublicController::class, 'submit'])
    ->middleware('throttle:satisfaction-survey-submit')
    ->name('survey.submit');

Route::get('/{locale?}', function (?string $locale = null) {
    $locale = in_array($locale, ['en', 'ar', 'tr'], true) ? $locale : 'en';

    return view('landing', [
        'content' => LandingPageContent::hub($locale),
        'locale' => $locale,
    ]);
})->where('locale', 'en|ar|tr')->name('home');

Route::get('/{specialtySlug}', function (string $specialtySlug) {
    $specialty = LandingPageContent::specialtyKeyForSlug($specialtySlug);
    abort_unless($specialty, 404);

    return view('landing-specialty', [
        'content' => LandingPageContent::specialty($specialty, 'en'),
        'specialty' => $specialty,
        'specialtySlug' => $specialtySlug,
        'accent' => LandingPageContent::SPECIALTY_ACCENTS[$specialty],
        'locale' => 'en',
    ]);
})->where('specialtySlug', implode('|', LandingPageContent::SPECIALTY_SLUGS))->name('specialty.home');

Route::get('/{locale}/{specialtySlug}', function (string $locale, string $specialtySlug) {
    $specialty = LandingPageContent::specialtyKeyForSlug($specialtySlug);
    abort_unless($specialty, 404);

    return view('landing-specialty', [
        'content' => LandingPageContent::specialty($specialty, $locale),
        'specialty' => $specialty,
        'specialtySlug' => $specialtySlug,
        'accent' => LandingPageContent::SPECIALTY_ACCENTS[$specialty],
        'locale' => $locale,
    ]);
})->where(['locale' => 'en|ar|tr', 'specialtySlug' => implode('|', LandingPageContent::SPECIALTY_SLUGS)])->name('specialty');

Route::post('/contact', [LandingPageInquiryController::class, 'storeContact'])->middleware('throttle:5,1')->name('landing.contact.store');
Route::post('/quote', [LandingPageInquiryController::class, 'storeQuote'])->middleware('throttle:5,1')->name('landing.quote.store');

Route::get('/book/{company:booking_slug}', function (Company $company) {
    return view('public-booking', [
        'company' => $company,
        'locale' => request()->string('lang')->value() ?: 'en',
    ]);
})->where('company', '[a-z0-9-]+')->name('booking.default');

Route::get('/{locale}/book/{company:booking_slug}', function (string $locale, Company $company) {
    return view('public-booking', ['company' => $company, 'locale' => $locale]);
})->where(['locale' => 'en|ar|tr', 'company' => '[a-z0-9-]+'])->name('booking');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login')->name('admin.login.store');
        Route::get('login/otp', [AdminAuthController::class, 'showOtp'])->name('admin.login.otp');
        Route::post('login/otp', [AdminAuthController::class, 'verifyOtp'])->middleware('throttle:admin-login-otp-verify')->name('admin.login.otp.verify');
        Route::post('login/otp/resend', [AdminAuthController::class, 'resendOtp'])->middleware('throttle:admin-login-otp-resend')->name('admin.login.otp.resend');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        Route::get('system', [AdminSystemStatusController::class, 'show'])->name('admin.system.show');
        Route::post('system/test-job', [AdminSystemStatusController::class, 'testJob'])->middleware('throttle:10,1')->name('admin.system.test-job');

        Route::get('companies', [AdminCompanyController::class, 'index'])->name('admin.companies.index');
        Route::post('companies', [AdminCompanyController::class, 'store'])->name('admin.companies.store');
        // withTrashed(): a deleted company must still be openable (to show
        // its Deleted state and a Restore button) instead of 404ing like any
        // other soft-deleted implicit-binding lookup would.
        Route::get('companies/{company}', [AdminCompanyController::class, 'show'])->name('admin.companies.show')->withTrashed();
        Route::put('companies/{company}', [AdminCompanyController::class, 'update'])->name('admin.companies.update');
        Route::delete('companies/{company}', [AdminCompanyController::class, 'destroy'])->name('admin.companies.destroy');
        Route::patch('companies/{company}/restore', [AdminCompanyController::class, 'restore'])->name('admin.companies.restore')->withTrashed();
        // withTrashed(): only an already soft-deleted company can be
        // permanently erased -- see the controller method's own guard.
        Route::delete('companies/{company}/force-delete', [AdminCompanyController::class, 'forceDestroy'])->name('admin.companies.force-delete')->withTrashed();

        Route::post('users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::patch('users/{user}/restore', [AdminUserController::class, 'restore'])->name('admin.users.restore')->withTrashed();
        Route::delete('users/{user}/force-delete', [AdminUserController::class, 'forceDestroy'])->name('admin.users.force-delete')->withTrashed();

        Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])->name('admin.subscriptions.store');
        Route::put('subscriptions/{subscription}', [AdminSubscriptionController::class, 'update'])->name('admin.subscriptions.update');
        Route::delete('subscriptions/{subscription}', [AdminSubscriptionController::class, 'destroy'])->name('admin.subscriptions.destroy');
        Route::patch('subscriptions/{subscription}/toggle-status', [AdminSubscriptionController::class, 'toggleStatus'])->name('admin.subscriptions.toggle-status');
        Route::patch('subscriptions/{subscription}/restore', [AdminSubscriptionController::class, 'restore'])->name('admin.subscriptions.restore')->withTrashed();
        Route::delete('subscriptions/{subscription}/force-delete', [AdminSubscriptionController::class, 'forceDestroy'])->name('admin.subscriptions.force-delete')->withTrashed();
        Route::patch('companies/{company}/toggle-status', [AdminCompanyController::class, 'toggleStatus'])->name('admin.companies.toggle-status');

        Route::get('landing-page', [AdminLandingPageController::class, 'edit'])->name('admin.landing-page.edit');
        Route::put('landing-page', [AdminLandingPageController::class, 'update'])->name('admin.landing-page.update');

        Route::get('inquiries', [AdminLandingPageInquiryController::class, 'index'])->name('admin.inquiries.index');
        Route::patch('inquiries/{inquiry}/read', [AdminLandingPageInquiryController::class, 'markRead'])->name('admin.inquiries.read');
        Route::delete('inquiries/{inquiry}', [AdminLandingPageInquiryController::class, 'destroy'])->name('admin.inquiries.destroy');
    });
});
