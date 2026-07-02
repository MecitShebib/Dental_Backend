# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Laravel 12 REST API backend for a dental clinic management system, with a separate web-based admin panel. It uses Laravel Sanctum for API token authentication and SQLite by default (configurable to MySQL/PostgreSQL via `.env`).

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

**Mobile API** (`routes/api.php`, prefix `/api`): Stateless, Sanctum token-based. Login is a two-step OTP flow — `POST /auth/login` issues a challenge, `POST /auth/login/verify-otp` verifies it and returns a bearer token. OTP codes are delivered via Turkey SMS (`MobileOtpService`) when `TURKEYSMS_ENABLED=true`; otherwise a random 6-digit code is logged to the console for local dev.

**Admin Panel** (`routes/web.php`): Session-based, email+password. Only users with `is_project_admin=true` can access it. Admin routes use the `EnsureAdminUser` middleware.

### Multi-tenancy model

Every `User` and all clinical data belong to a `Company`. A `Company` must have an active `Subscription` (checked via `SubscriptionAccessService`) for its users to log in. `Company::currentSubscription()` defines what "active" means: status=active, started on or before today, not yet ended.

### Data models and relationships

- **Client** — the dental patient. Has one `TreatmentRecord` (the overall treatment plan with per-tooth data in `TreatmentRecordTooth`), many `Visit`s, `Payment`s, and `Appointment`s.
- **Appointment** — scheduled slot for a client with a doctor. States: `scheduled → checked-in / no-show / cancelled`. When checked in, a `Visit` is created linked to the appointment.
- **Visit** — an actual clinical visit. Can be created directly (walk-in) or from an appointment check-in. Linked to `Payment`s.
- **DoctorSchedule / DoctorScheduleDay** — a doctor's weekly working hours and slot size, used by `DoctorAvailabilityService` to compute free/filled slots and validate appointment times.

### Service layer

| Service | Responsibility |
|---|---|
| `MobileOtpService` | Issue, verify, and expire OTP challenges; normalize phone numbers |
| `SubscriptionAccessService` | Gate login by company status and subscription |
| `CompanyUserLimitService` | Enforce `max_users` from the active subscription |
| `AppointmentConflictService` | Validate that a new appointment fits within working hours and doesn't overlap existing ones |
| `DoctorAvailabilityService` | Return slot grid, available start times, and available durations for a doctor on a date |
| `AppointmentActionStateService` | Determine UI action state (`manage` / `checkin` / `locked`) based on appointment time proximity |
| `TreatmentRecordService` | Persist per-tooth treatment data |
| `ClientFinancialSummaryService` | Compute total services, total paid, and remaining balance for a client |
| `AiTreatmentPlanService` | Turn a doctor's case description into a multi-session treatment plan (preview) and persist it as appointments (confirm), via `OpenAiClient` and the existing scheduling services |
| `OpenAiClient` | Thin wrapper over OpenAI's chat completions (structured JSON output) and Whisper transcription HTTP APIs |

### UUID pattern

All models use the `HasUuid` trait (`app/Models/Concerns/HasUuid.php`), which auto-assigns a UUID on creation. Both `id` (integer PK) and `uuid` (string) fields exist; the API exposes `uuid` to clients.

### Enums

Backed PHP enums under `app/Enums/` for: `UserStatus`, `ClientGender`, `ClientStatus`, `AppointmentType`, `AppointmentStatus`, `AttendanceStatus`, `PaymentMethod`, `Weekday`, `SubscriptionStatus`. Models cast these fields automatically.

## Environment configuration

Copy `.env.example` to `.env` before first run. Key non-obvious settings:

- `TURKEYSMS_ENABLED` — set to `false` locally to skip real SMS; OTP is printed to the Laravel log instead.
- `DB_CONNECTION` — defaults to `sqlite`; change to `mysql` and set `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD` for production.
- `QUEUE_CONNECTION=database` — requires the queue worker (`php artisan queue:listen`) to be running for background jobs.
- `OPENAI_API_KEY` — required for the AI treatment plan assistant (`AiTreatmentPlanController`/`AiTreatmentPlanService`), which calls OpenAI for chat completions and Whisper transcription. `OPENAI_CHAT_MODEL`/`OPENAI_WHISPER_MODEL` default to `gpt-4o-mini`/`whisper-1`.
- The AI treatment plan and manual treatment-record odontogram images are served from the `public` disk — run `php artisan storage:link` (already part of `composer run setup`) so `planned_image_url`/`odontogram_image_url` resolve over HTTP.
