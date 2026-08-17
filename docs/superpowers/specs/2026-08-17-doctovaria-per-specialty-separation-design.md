# Doctovaria Per-Specialty Interface Separation — Design

Date: 2026-08-17
Repos affected: `Dental_Backend` (Laravel API) and `Dental_FrontEnd` (React/Vite) — both get real
structural changes under this spec.

## 1. Problem & Goal

Phase 8 of the Doctovaria multi-specialty platform (see the project's `project_doctovaria_multispecialty_platform.md`
memory) gave every specialty correct *data* separation — a doctor sees only their own patients,
a system manager sees a specialty's patients when "inside" that specialty's app — but all five
specialties render through the exact same shared page components (`PatientsPage.jsx`,
`ClientDetailsPage.jsx`, etc.), just filtered by an `activeSpecialtyKey` value in context. The
user explicitly rejected this as too shallow: they want each non-dental specialty to feel like
its own product, Zoho-CRM-vs-Zoho-Desk style — its own URL namespace, its own page files, its own
visual accent — while **dental's existing code stays completely untouched** (it has real users'
worth of tested behavior behind it and nothing here should risk it).

Goal: give Gynevaria, Medivaria, Orthovaria, and Estevaria genuinely separate frontend page files
and backend controller/route namespaces for their clinical core, without duplicating the
security-critical logic underneath (tenant isolation, specialty scoping, doctor-ownership) or
touching dental's files at all.

## 2. Scope boundaries

**In scope** — for each of Gynevaria / Medivaria / Orthovaria / Estevaria:

- A dedicated frontend page set for the 5 clinical pages: Patients (list), Client Details, Client
  Edit, Dashboard, Appointments.
- A dedicated `/{specialty}/...` URL namespace for those 5 pages.
- A dedicated backend controller namespace + route file for the same clinical surface.
- A distinct accent color + icon (already-existing `brand_name`/`icon` from `specialties` table)
  applied to the copied pages — layout, typography, and sidebar chrome stay identical across all
  five (approved design option: "same skeleton, different accent color").
- Extraction of the query/authorization logic currently living inside the shared
  `ClientController`/`AppointmentController`/`DashboardController` into shared service classes,
  so the new per-specialty controllers (and dental's existing one) all delegate to one tested
  implementation.

**Explicitly out of scope**:

- **Dental's own files** — `src/pages/PatientsPage.jsx`, `ClientDetailsPage.jsx`,
  `ClientEditPage.jsx`, `DashboardPage.jsx`, `AppointmentsPage.jsx`, and
  `app/Http/Controllers/Api/ClientController.php` / `AppointmentController.php` /
  `DashboardController.php` are not moved, renamed, or edited beyond the service-extraction
  refactor in §4 (which preserves their existing behavior 1:1, verified by the existing 421-test
  suite passing unchanged). Dental gets no new backend namespace and no new frontend page copies.
- The 19 non-clinical frontend pages (Login, Register, ForgotPassword, OtpVerification, Settings,
  MyProfile, Users, UserDetails, UserEdit, CompanyProfile, CompanyDashboard, Subscriptions,
  Branches, MessageTemplates, Inventory, CallLogs, XrayGallery, Accounting/\*, Lab/\*, Reports/\*,
  Launcher) — these don't vary by specialty today and stay fully shared, unprefixed, untouched.
- Database schema changes — `clients`, `appointments`, `treatment_charges` etc. stay the single
  shared tables from Phase 8; "a patient record is shared across specialties" is unchanged.
- Any new specialty-specific *fields* (e.g., a gynecology-only LMP/EDD column on the patients
  list) — this spec only establishes the separated file/route structure. Specialty-specific
  content beyond a copy-paste starting point is a natural follow-up once the structure exists, not
  part of this build.
- Real clinical review of the four specialties' milestone care-plan schedules — already flagged
  as a separate, non-code concern in earlier Doctovaria phases.

## 3. Decisions made during brainstorming (with reasoning)

These were resolved via a multi-round Q&A with the user before this spec was written:

1. **Build everything in one push**, using Gynevaria as the reference implementation first (it
   already has the most real backend behind it — `PrenatalCarePlanService`, its own confirm
   endpoint), then mechanically repeating the same pattern for Medivaria/Orthovaria/Estevaria in
   the same engagement, not as separately-scheduled future work.
2. **Routing is symmetric** — dental also gets a `/dental/...` URL namespace for its 5 clinical
   pages, for consistency of the URL scheme across all five specialties. This is a routing-layer
   change only; dental's page/component files are not touched to achieve it (see §5).
3. **Design differentiation is shallow by design** — identical sidebar chrome, typography, and
   layout across all specialties; only accent color + icon + brand name differ. The user picked
   this explicitly over a heavier "different layout per specialty" or "fully independent visual
   language" option, partly because this project already went through (and rejected) two more
   elaborate design directions before landing on its current "Enterprise" identity — repeating
   that risk five times over was judged not worth it.
4. **Backend split uses a thin-controller-over-shared-service pattern**, not full duplication.
   The user initially asked for full per-specialty controller duplication; when shown the
   concrete risk (a tenant-isolation or specialty-scoping bug fixed in one of five duplicated
   copies doesn't fix the other four, and nothing forces you to notice), they chose the layered
   approach instead: separate, real controller classes and route files per specialty (satisfying
   the "feels like separate projects" goal), all delegating to one shared, tested service
   implementation per concern.
5. **Frontend duplication is scoped to the 5 clinical pages only**, not all ~24 pages in the app.
   The other 19 pages are staff/company-management surfaces that don't vary by specialty and
   duplicating them would be pure maintenance cost with no user-facing benefit.

## 4. Backend architecture

### 4.1 Extract shared query/authorization services

Today's specialty-aware filtering logic (added in Doctovaria Phase 8) lives inline inside
`app/Http/Controllers/Api/ClientController.php`, `AppointmentController.php`, and
`DashboardController.php`. Extract it — without behavior changes — into:

- `app/Services/Clinical/ClientQueryService.php` — the `index()` query building (doctor
  hard-scoped to `specialty_id = own AND primary_doctor_id = own`; non-doctor scoped by an
  explicit specialty argument) plus the `store()`/`ensureEnrolled` logic already provided by
  `ClientSpecialtyEnrollmentService` (that service is reused as-is, not duplicated).
- `app/Services/Clinical/AppointmentQueryService.php` — the `index()` specialty filter
  (`whereHas('doctor', ...)`) and the existing conflict/creation logic delegation.
- `app/Services/Clinical/DashboardStatsService.php` — the `stats()` specialty-filtered
  appointment/payment aggregation.

Each existing controller (`Api\ClientController`, etc.) is refactored to call into its matching
service instead of inlining the query — this is a **behavior-preserving refactor**, verified by
running the existing test suite unchanged before writing any new controller. Dental's request
path is: `Api\ClientController` → `ClientQueryService::forSpecialty('dental', ...)` — same
runtime behavior as today, just relocated.

### 4.2 Per-specialty thin controllers + routes

For each of Gynevaria/Medivaria/Orthovaria/Estevaria:

```
app/Http/Controllers/Api/Gynecology/ClientController.php       (~10-15 lines)
app/Http/Controllers/Api/Gynecology/AppointmentController.php
app/Http/Controllers/Api/Gynecology/DashboardController.php
```

Each method body is a one-line delegation, e.g.:

```php
public function index(IndexClientRequest $request)
{
    return ClientResource::collection(
        $this->clientQuery->list($request, specialty: Specialty::GYNECOLOGY)
    );
}
```

No query building, no authorization logic, no validation duplicated — those stay in the shared
service and existing FormRequest classes (reused as-is).

New route files, one per specialty, `require`d from `routes/api.php` under a specialty prefix:

```
routes/api/gynecology.php       → prefix /api/gynecology
routes/api/internal_medicine.php → prefix /api/internal_medicine
routes/api/orthopedics.php       → prefix /api/orthopedics
routes/api/cosmetic.php          → prefix /api/cosmetic
```

Dental keeps using the existing unprefixed `/api/clients`, `/api/appointments`, `/api/dashboard`
endpoints — no new route file, no new namespace, `routes/api.php` untouched for dental's own
routes.

### 4.3 Testing

- One parametrized test suite per shared service (e.g. `ClientQueryServiceTest`) that runs the
  same tenant-isolation / doctor-ownership assertions across all five specialty keys
  (`['dental', 'gynecology', 'internal_medicine', 'orthopedics', 'cosmetic']`) — this is what
  actually protects against the risk that motivated the thin-controller design: one test suite,
  one place to catch a regression, instead of five independent suites that could drift.
- A thin "route resolves and delegates correctly" test per new per-specialty controller —
  confirms wiring, not business logic (business logic is already covered by §4.3's parametrized
  suite).
- Existing dental test suite (421 tests as of the last Doctovaria phase) must stay green
  unchanged throughout — it's the regression guard for the extraction in §4.1.

## 5. Frontend architecture

### 5.1 Folder structure

```
src/
  specialties/
    gynecology/
      pages/
        GynecologyPatientsPage.jsx        (copy of PatientsPage.jsx, 342 lines as of writing)
        GynecologyClientDetailsPage.jsx   (copy of ClientDetailsPage.jsx, 487 lines)
        GynecologyClientEditPage.jsx      (copy of ClientEditPage.jsx, 100 lines)
        GynecologyDashboardPage.jsx       (copy of DashboardPage.jsx, 549 lines)
        GynecologyAppointmentsPage.jsx    (copy of AppointmentsPage.jsx, 384 lines)
      theme.js                           (accent color + icon token)
    internal_medicine/pages/...           (same 5 files, Medivaria)
    orthopedics/pages/...                 (same 5 files, Orthovaria)
    cosmetic/pages/...                    (same 5 files, Estevaria)
  pages/                                  ← UNCHANGED: dental's 5 clinical pages stay here and
                                            keep being used directly by dental's routes, plus all
                                            19 shared non-clinical pages
```

Each copied page keeps calling the same generic API endpoints it does today for its specialty
(`/api/gynecology/clients` instead of `/api/clients?specialty=gynecology` — a straight endpoint
swap, not a new data-fetching pattern) via the existing shared `lib/api.js` client and
`AppStateApiContext.jsx` — neither of those files gets duplicated.

### 5.2 Routing

Only the 5 clinical routes get a specialty prefix; the 19 shared pages stay exactly as they are
(`/settings`, `/users`, etc. — not specialty things, prefixing them would be meaningless).

```jsx
// Dental — existing components, only a route path change (App.jsx is a shared file; editing
// its route table is not "editing dental's code" in the sense the user meant)
<Route path="/dental/patients" element={<PatientsPage />} />
<Route path="/dental/dashboard" element={<DashboardPage />} />
<Route path="/dental/appointments" element={<AppointmentsPage />} />
<Route path="/dental/client-details/:clientId" element={<ClientDetailsPage />} />
<Route path="/dental/client-edit/:clientId?" element={<ClientEditPage />} />

// Gynevaria — new components
<Route path="/gynecology/patients" element={<GynecologyPatientsPage />} />
<Route path="/gynecology/dashboard" element={<GynecologyDashboardPage />} />
// ...same pattern for internal_medicine, orthopedics, cosmetic
```

The pre-existing unprefixed routes (`/patients`, `/dashboard`, `/appointments`,
`/client-details/:id`, `/client-edit/:id`) are **kept as redirects** to their `/dental/...`
equivalents, so existing bookmarks/browser history/any hardcoded `navigate("/patients")` call
site don't silently break. New `navigate()` calls in dental's own pages are not required to
change (they still land correctly via the redirect); updating them to call the `/dental/...` path
directly is a nice-to-have cleanup, not required by this spec.

### 5.3 Navigation

`data/mockData.js`'s `NAV_ITEMS` entries for `patients`/`dashboard`/`appointments` currently hold
a static `path` string. These three become a function of `activeSpecialtyKey` (already available
in `AppStateApiContext.jsx` since Phase 8), resolved in `Sidebar.jsx` where `NAV_ITEMS` is
consumed — e.g. `patients` resolves to `` `/${activeSpecialtyKey}/patients` ``. The other 19 nav
entries are untouched.

### 5.4 Theming

Each `specialties/{key}/theme.js` exports an accent color + icon. The copied pages render inside
a small shared wrapper component (`components/SpecialtyShell.jsx`, new, ~20 lines) that sets a
CSS custom property (e.g. `--specialty-accent`) from the theme module; existing Enterprise design
tokens (`--surface-1`, `--text-*`, `--border-subtle`, per this project's established "Cusp"
token system) are unaffected — only the one accent variable is overridden per specialty. Dental
does not use `SpecialtyShell` — it keeps its current accent (`--specialty-accent` defaults to the
existing dental blue) with zero code change.

## 6. Rollout order

1. **Gynevaria (reference implementation)** — do the §4.1 service extraction (with the existing
   suite green throughout), then build Gynevaria's backend namespace and frontend page set on top
   of it, end to end, verified with a real Playwright pass.
2. **Medivaria, Orthovaria, Estevaria** — mechanical repetition of the now-proven pattern: no
   further service extraction needed (already done in step 1), just the thin controllers, route
   file, page copies, and theme token for each. Lighter smoke-test verification per specialty
   (route resolves, correct data isolation, correct accent) rather than a full Playwright pass
   each time.
3. Dental is not touched in any step.

## 7. Open risk, explicitly accepted

Five near-identical copies of each clinical page (25 files total instead of 5) means a future bug
fix or feature addition to "the patients page" has to be applied five times if it should apply
everywhere, or once if it's genuinely specialty-specific. This is the direct cost of the user's
explicit goal (structural independence, each specialty free to diverge without coordinating with
the others) and is not something this spec tries to work around on the frontend side — only the
backend's security-critical logic gets the shared-service protection from §4.1, because that's
where undetected drift is dangerous rather than just inconvenient.
