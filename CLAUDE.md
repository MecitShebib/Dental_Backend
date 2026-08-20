# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Laravel 12 REST API backend for a dental clinic management system, with a separate web-based admin panel (`/admin`) and a public marketing landing page (`/{locale?}`, `en|ar|tr`, `resources/views/landing.blade.php`) whose content is editable from the admin panel (`Admin\LandingPageController`, `LandingPageContent` model — see `app/Models/LandingPageContent.php` for the per-locale defaults/schema). The landing page also has Contact and Get-a-Quote forms that save to `landing_page_inquiries` (`LandingPageInquiry` model, `InquiryType` enum) and are reviewed at `/admin/inquiries`. It uses Laravel Sanctum for API token authentication and SQLite by default (configurable to MySQL/PostgreSQL via `.env`).

**Deployment**: this app and the separate `Dental_FrontEnd` React SPA deploy together on one server/domain, split by path (`/`, `/admin`, `/api` here; `/app` for the frontend) — see `DEPLOYMENT.md` in the `Dental_FrontEnd` repo for the full Nginx setup.

**Multi-specialty platform ("Doctovaria")**: this product is an umbrella platform of 5 specialties sharing one Laravel+React codebase — Dentavaria (dental), Gynevaria (gynecology), Medivaria (internal medicine), Orthovaria (orthopedics), Estevaria (cosmetic). See `app/Models/Specialty.php` and the `specialties` table (`key`/`brand_name`/`is_active`). Every specialty now has a real, if narrow, v1 backend+frontend — each `App\Specialties\{Specialty}\{Specialty}Module::isBuilt()` returns `true`, and each has its own care-plan workflow (`App\Services\CarePlanService` for the generic scheduling/charging engine, `App\Services\MilestoneCarePlanService` for the "fixed list of visits N days after an anchor date" shape Medivaria/Orthovaria/Estevaria share, `App\Specialties\Gynecology\PrenatalCarePlanService` kept separate since it has real domain math). `Subscription`, `User` (doctors only), and `TreatmentCatalog` all carry a nullable `specialty_id`. A company can hold one active `Subscription` per specialty (the admin "Create Subscription" form can select several specialties at once, creating one row per specialty — see `Admin\SubscriptionController::store()`); capacity limits (`max_users`/`max_ai_tokens`/`max_branches`) are a pooled company-wide total across every active subscription, not gated per specialty — see `Company::aggregatedSubscriptionLimit()`/`aggregatedSubscriptionUsage()`, used by `AiTokenUsageService`/`CompanyUserLimitService`/`CompanyBranchLimitService`. `Company::currentSubscription()` (singular "the latest active subscription regardless of specialty") is still used as-is by the plain existence checks (`EnsureActiveClinicAccess`, `SubscriptionAccessService`) and by display-only eager-loads (`CompanyResource`, `AuthController`, the admin panel) — fine since those don't need to pick a specific specialty.

**Patient/specialty data separation (Zoho-CRM-Deal-like)**: `client_specialty_records` (`ClientSpecialtyRecord` model) is the join that makes a `Client` (the one shared person record) a "patient" of a given specialty — `client_id` + `specialty_id`, unique together, plus a nullable `primary_doctor_id` (the record's "owner"). `App\Services\ClientSpecialtyEnrollmentService::ensureEnrolled($client, $doctor)` creates/claims this record and is called from every place an Appointment or Visit gets created (`AppointmentController`, `ClientVisitController`, `CarePlanService`, `AiTreatmentPlanService`, `PublicBookingService`, `ClientController::store()`) — a second doctor of the same specialty never steals an already-claimed patient. Read-side filtering: a doctor acting user is hard-scoped to `specialty_id = own AND primary_doctor_id = own` (ignores any `?specialty=` param); a non-doctor is scoped by an explicit `?specialty=` query param if given (`ClientController::index()`, `DashboardController::stats()`, `AppointmentController::index()`), else unfiltered. On the frontend, `activeSpecialtyKey` (`AppStateApiContext.jsx`) tracks which specialty "app" the user is currently inside — auto-set to a doctor's own specialty, or a single-specialty company's one option, or explicitly by `LauncherPage.jsx` when staff at a multi-specialty company pick a tile; `Sidebar.jsx` shows the active specialty's name and (non-doctors only) a "switch app" icon back to the launcher.

## Commands

```bash
# First-time setup
composer run setup

# Development (starts server, queue, logs, and Vite simultaneously)
composer run dev

# Run all tests
composer run test

# Run a single test file
php artisan test tests/Feature/AuthOtpFlowTest.php

# Run a single test method
php artisan test --filter=test_method_name

# Code style (Laravel Pint)
./vendor/bin/pint

# Database migrations
php artisan migrate

# Interactive REPL
php artisan tinker
```

## Architecture

### Dual authentication surfaces

**Mobile API** (`routes/api.php`, prefix `/api`): Stateless, Sanctum token-based. Login is a two-step OTP flow — `POST /auth/login` issues a challenge, `POST /auth/login/verify-otp` verifies it and returns a bearer token. OTP codes are delivered via Infobip (`MobileOtpService`) when `INFOBIP_ENABLED=true`; otherwise a random 6-digit code is logged to the console for local dev.

**Admin Panel** (`routes/web.php`): Session-based, email+password. Only users with `is_project_admin=true` can access it. Admin routes use the `EnsureAdminUser` middleware.

### Multi-tenancy model

Every `User` and all clinical data belong to a `Company`. A `Company` must have an active `Subscription` (checked via `SubscriptionAccessService`) for its users to log in. `Company::currentSubscription()` defines what "active" means: status=active, started on or before today, not yet ended. A subscription also caps AI usage (`max_ai_tokens`/`ai_tokens_used`, enforced by `AiTokenUsageService`) — `null` means unlimited.

### Data models and relationships

- **Client** — the dental patient. Has one `TreatmentRecord`, many `Visit`s, `Payment`s, `Appointment`s, and `TreatmentCharge`s (see the pricing/billing section below — this is what actually drives a client's balance, not `TreatmentRecord`).
- **Appointment** — scheduled slot for a client with a doctor. States: `scheduled → checked-in / no-show / cancelled`. When checked in, a `Visit` is created linked to the appointment.
- **Visit** — an actual clinical visit. Can be created directly (walk-in) or from an appointment check-in. Linked to `Payment`s.
- **DoctorSchedule / DoctorScheduleDay** — a doctor's weekly working hours and slot size, used by `DoctorAvailabilityService` to compute free/filled slots and validate appointment times.

### Treatment pricing & billing ledger

- **`TreatmentCatalog`** (`treatment_catalog` table) is per-company and has two `scope`s: `company` (a handful of manually-curated services, e.g. consultation/filling/crown — editable in Settings > Pricing) and `odontogram` (~100 auto-seeded rows, one per procedure/condition the vendored odontogram-v2 widget can select, `code` = `"{category}:{value}"` e.g. `fillingMaterial:composite`). Both scopes are visible/editable from Settings > Pricing. `TreatmentCatalogSeeder::seedCompany()` seeds both scopes for every company and runs automatically on company creation (`Admin\CompanyController::store()`) — new procedure codes/prices are added there, kept in sync with `OdontogramV2Vocabulary` (the same enum list the AI's JSON schema is constrained to) and the frontend's odontogram pricing map.
- **`TreatmentCharge`** (`treatment_charges` table) is the single source of truth for what a client owes — a flat ledger of line items (`{client_id, source_type, source_id, amount, description}`), never one aggregate row per client. `source_type` is `manual` / `ai_plan` / `visit` / `appointment`; a discount is just another row with a negative `amount`. `ClientFinancialSummaryService` sums this table directly (`total_services_amount`, `total_paid_amount` from `Payment`, `remaining_amount`).
- **`TreatmentChargeService`** is the one place charge-lifecycle logic lives: `syncItems($client, $sourceType, $sourceId, $items)` does a full delete-then-recreate for that source (visit/appointment/AI-plan-session saves always send their *complete* current set of line items, since a partial payload would silently drop the rest); `retarget()` re-points a source's charges (e.g. appointment → visit on check-in) without losing them; `deleteAllForAppointment()` clears both possible source types for an appointment id. The one exception to "one source, one owner" is `source_type=manual` with a `null` source_id (the AI-plan "add extra charge" endpoint) — those rows are *appended*, never replaced, because every manual charge for a client shares that same null-source key.
- **Do not resurrect `treatment_records.total_services_amount`/`TreatmentRecordTooth`** — this is legacy plumbing for the retired V1 odontogram; the current frontend's `TreatmentRecordService::update()` call never sends a `teeth` payload, so it's always zero. `TreatmentRecord.notes` is also overloaded: the V2 frontend serializes the odontogram's full JSON state into it (see `parseOdontogramV2Note`/`serializeOdontogramV2Note` client-side), not free-text notes.

### Service layer

| Service | Responsibility |
|---|---|
| `MobileOtpService` | Issue, verify, and expire OTP challenges; normalize phone numbers |
| `SubscriptionAccessService` | Gate login by company status and subscription |
| `CompanyUserLimitService` | Enforce `max_users` from the active subscription |
| `AiTokenUsageService` | Gate and record AI chat-completion token usage against a subscription's `max_ai_tokens` cap (Whisper transcription is not metered) |
| `AppointmentConflictService` | Validate that a new appointment fits within working hours and doesn't overlap existing ones |
| `DoctorAvailabilityService` | Return slot grid, available start times, and available durations for a doctor on a date |
| `AppointmentActionStateService` | Determine UI action state (`manage` / `checkin` / `locked`) based on appointment time proximity |
| `TreatmentRecordService` | Persist the legacy per-tooth treatment record (see note above — not the billing source of truth) |
| `TreatmentChargeService` | Create/replace/retarget/delete `TreatmentCharge` line items for a visit, appointment, or AI-plan session |
| `ClientFinancialSummaryService` | Compute total services, total paid, and remaining balance for a client from `TreatmentCharge` + `Payment` |
| `AiTreatmentPlanService` | Turn a doctor's case description into a multi-session treatment plan (preview) and persist it as appointments with itemized charges (confirm), via `OpenAiClient` and the existing scheduling/pricing services |
| `OdontogramV2Vocabulary` | The enum of every tooth-selection/procedure/condition value the odontogram-v2 widget supports — constrains both the AI's JSON schema and what `TreatmentCatalogSeeder` prices |
| `OpenAiClient` | Thin wrapper over OpenAI's chat completions (structured JSON output) and Whisper transcription HTTP APIs |

`AiTreatmentPlanController` has two endpoints beyond the obvious preview/confirm: `transcribe` (Whisper-only, called as soon as a recording stops so the transcript can be reviewed/edited before generating a plan — deliberately *not* gated by the AI token cap) and `addCharge` (appends extra manual `TreatmentCharge` line items, e.g. a consultation fee not captured by the odontogram).

### UUID pattern

All models use the `HasUuid` trait (`app/Models/Concerns/HasUuid.php`), which auto-assigns a UUID on creation. Both `id` (integer PK) and `uuid` (string) fields exist; the API exposes `uuid` to clients.

### Enums

Backed PHP enums under `app/Enums/` for: `UserStatus`, `ClientGender`, `ClientStatus`, `AppointmentType`, `AppointmentStatus`, `AttendanceStatus`, `PaymentMethod`, `Weekday`, `SubscriptionStatus`, `InquiryType`. Models cast these fields automatically.

## Environment configuration

Copy `.env.example` to `.env` before first run. Key non-obvious settings:

- `INFOBIP_ENABLED` — set to `false` locally to skip real SMS; OTP is printed to the Laravel log instead.
- `DB_CONNECTION` — defaults to `sqlite`; change to `mysql` and set `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD` for production.
- `QUEUE_CONNECTION=database` — requires the queue worker (`php artisan queue:listen`) to be running for background jobs.
- `OPENAI_API_KEY` — required for the AI treatment plan assistant (`AiTreatmentPlanController`/`AiTreatmentPlanService`), which calls OpenAI for chat completions and Whisper transcription. `OPENAI_CHAT_MODEL`/`OPENAI_WHISPER_MODEL` default to `gpt-4o-mini`/`whisper-1`.
- The AI treatment plan and manual treatment-record odontogram images are served from the `public` disk — run `php artisan storage:link` (already part of `composer run setup`) so `planned_image_url`/`odontogram_image_url` resolve over HTTP.
