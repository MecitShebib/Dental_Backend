<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LandingPageController as AdminLandingPageController;
use App\Http\Controllers\Admin\LandingPageInquiryController as AdminLandingPageInquiryController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\LandingPageInquiryController;
use App\Http\Controllers\SatisfactionSurveyPublicController;
use App\Models\Company;
use App\Models\LandingPageContent;
use App\Support\ApiDocumentation;
use App\Support\LegalContent;
use Illuminate\Support\Facades\Route;

Route::get('/api-docs', function () {
    return view('api-docs', [
        'groups' => ApiDocumentation::groups(),
        'enums' => ApiDocumentation::enums(),
        'baseUrl' => ApiDocumentation::baseUrl(),
    ]);
})->name('api-docs');

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

Route::post('/contact', [LandingPageInquiryController::class, 'storeContact'])->name('landing.contact.store');
Route::post('/quote', [LandingPageInquiryController::class, 'storeQuote'])->name('landing.quote.store');

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
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        Route::get('companies', [AdminCompanyController::class, 'index'])->name('admin.companies.index');
        Route::post('companies', [AdminCompanyController::class, 'store'])->name('admin.companies.store');
        Route::get('companies/{company}', [AdminCompanyController::class, 'show'])->name('admin.companies.show');
        Route::put('companies/{company}', [AdminCompanyController::class, 'update'])->name('admin.companies.update');
        Route::delete('companies/{company}', [AdminCompanyController::class, 'destroy'])->name('admin.companies.destroy');

        Route::post('users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle-status');

        Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])->name('admin.subscriptions.store');
        Route::put('subscriptions/{subscription}', [AdminSubscriptionController::class, 'update'])->name('admin.subscriptions.update');
        Route::delete('subscriptions/{subscription}', [AdminSubscriptionController::class, 'destroy'])->name('admin.subscriptions.destroy');
        Route::patch('subscriptions/{subscription}/toggle-status', [AdminSubscriptionController::class, 'toggleStatus'])->name('admin.subscriptions.toggle-status');
        Route::patch('companies/{company}/toggle-status', [AdminCompanyController::class, 'toggleStatus'])->name('admin.companies.toggle-status');

        Route::get('landing-page', [AdminLandingPageController::class, 'edit'])->name('admin.landing-page.edit');
        Route::put('landing-page', [AdminLandingPageController::class, 'update'])->name('admin.landing-page.update');

        Route::get('inquiries', [AdminLandingPageInquiryController::class, 'index'])->name('admin.inquiries.index');
        Route::patch('inquiries/{inquiry}/read', [AdminLandingPageInquiryController::class, 'markRead'])->name('admin.inquiries.read');
        Route::delete('inquiries/{inquiry}', [AdminLandingPageInquiryController::class, 'destroy'])->name('admin.inquiries.destroy');
    });
});
