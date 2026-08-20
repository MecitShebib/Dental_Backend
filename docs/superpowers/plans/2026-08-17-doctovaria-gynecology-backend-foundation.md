# Gynevaria Backend Foundation (Doctovaria Per-Specialty Separation, Plan 1 of N) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the specialty-aware query/authorization logic that today lives inline inside
`Api\ClientController`, `Api\AppointmentController`, and `Api\DashboardController` into three
shared, directly-tested service classes (zero behavior change for dental), then build Gynevaria's
own thin backend controller namespace and route file on top of those same services.

**Architecture:** `app/Services/Clinical/{ClientQueryService,AppointmentQueryService,DashboardStatsService}.php`
hold the actual tenant-isolation / specialty-filtering / doctor-ownership logic, each covered by a
direct unit test parametrized across all 5 specialty keys. The existing `Api\ClientController` /
`Api\AppointmentController` / `Api\DashboardController` are refactored to delegate to them with
zero behavior change (guarded by the existing 421-test suite staying green). New
`Api\Gynecology\{ClientController,AppointmentController,DashboardController}` delegate to the
exact same services with the specialty hardcoded to `gynecology`, wired via a new
`routes/api/gynecology.php`. Dental's routes, controllers, and request/resource classes are not
touched beyond the internal delegation change.

**Tech Stack:** Laravel 12, PHPUnit (class-based, `extends TestCase`, `test_*` method names — this
is this codebase's existing convention, not Pest).

**Relationship to other plans:** This is Plan 1 of the rollout described in
`docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md`. Plan 2 (not yet
written) covers Gynevaria's *frontend* — it depends on the endpoints this plan creates. Plans for
Medivaria/Orthovaria/Estevaria come after Gynevaria (backend + frontend) is verified end-to-end,
since they mechanically repeat this plan's pattern and are far cheaper to write correctly once
there's a working reference to copy from.

---

## Task 1: `ClientQueryService` — failing test first

**Files:**
- Test: `tests/Unit/Services/Clinical/ClientQueryServiceTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Clinical\ClientQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public static function specialtyKeys(): array
    {
        return [
            'dental' => [Specialty::DENTAL],
            'gynecology' => [Specialty::GYNECOLOGY],
            'internal_medicine' => [Specialty::INTERNAL_MEDICINE],
            'orthopedics' => [Specialty::ORTHOPEDICS],
            'cosmetic' => [Specialty::COSMETIC],
        ];
    }

    private function makeClient(Company $company, string $name = 'Test Patient'): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    #[DataProvider('specialtyKeys')]
    public function test_a_doctor_only_sees_their_own_claimed_patients_regardless_of_specialty_key_argument(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);

        $ownPatient = $this->makeClient($company, 'Own Patient');
        $otherDoctorsPatient = $this->makeClient($company, 'Other Doctors Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($ownPatient, $doctor);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolled($otherDoctorsPatient, $otherDoctor);

        // Passing a DIFFERENT specialty key than the doctor's own must not matter --
        // a doctor is always hard-scoped to their own specialty_id (Doctovaria Phase 8).
        $result = app(ClientQueryService::class)->list($doctor, $specialtyKey, []);

        $this->assertCount(1, $result->items());
        $this->assertSame('Own Patient', $result->items()[0]->name);
    }

    #[DataProvider('specialtyKeys')]
    public function test_a_non_doctor_sees_only_patients_of_the_requested_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        $requestedSpecialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();

        $matchingPatient = $this->makeClient($company, 'Matching Patient');
        $otherPatient = $this->makeClient($company, 'Other Specialty Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($matchingPatient, $requestedSpecialty, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($otherPatient, $otherSpecialty, $manager);

        $result = app(ClientQueryService::class)->list($manager, $specialtyKey, []);

        $this->assertCount(1, $result->items());
        $this->assertSame('Matching Patient', $result->items()[0]->name);
    }

    public function test_name_and_phone_filters_are_applied(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $this->makeClient($company, 'Alice Match');
        $this->makeClient($company, 'Bob Nomatch');

        $result = app(ClientQueryService::class)->list($manager, null, ['name' => 'Alice']);

        $this->assertCount(1, $result->items());
        $this->assertSame('Alice Match', $result->items()[0]->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Clinical/ClientQueryServiceTest.php`
Expected: FAIL — `Class "App\Services\Clinical\ClientQueryService" not found`

## Task 2: Implement `ClientQueryService`

**Files:**
- Create: `app/Services/Clinical/ClientQueryService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services\Clinical;

use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The one place Client list-query scoping lives, shared by dental's own
 * ClientController and every per-specialty Api\{Specialty}\ClientController
 * -- see docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * A behavior-preserving extraction of what used to be inline in
 * Api\ClientController::index(); do not change the scoping rules here
 * without re-reading ClientSpecialtyEnrollmentService's docblock first.
 */
class ClientQueryService
{
    /**
     * @param  string|null  $specialtyKey  Only applied for a non-doctor acting user -- a doctor
     *                                     is always hard-scoped to their own specialty_id
     *                                     (Doctovaria Phase 8), regardless of this value.
     * @param  array{name?: ?string, phone?: ?string, per_page?: ?int}  $filters
     */
    public function list(User $actingUser, ?string $specialtyKey, array $filters): Paginator
    {
        return Client::query()
            ->with($this->nextAppointmentEagerLoad())
            ->when($actingUser->is_doctor, fn ($query) => $query->whereHas(
                'specialtyRecords',
                fn ($sq) => $sq->where('specialty_id', $actingUser->specialty_id)->where('primary_doctor_id', $actingUser->id)
            ))
            ->when(! $actingUser->is_doctor && $specialtyKey, function ($query) use ($specialtyKey) {
                $specialtyId = Specialty::query()->where('key', $specialtyKey)->value('id');
                $query->whereHas('specialtyRecords', fn ($sq) => $sq->where('specialty_id', $specialtyId));
            })
            ->when($filters['name'] ?? null, fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when($filters['phone'] ?? null, fn ($query) => $query->where('phone', 'like', '%'.$filters['phone'].'%'))
            ->latest()
            ->paginate($filters['per_page'] ?? null)
            ->withQueryString();
    }

    /**
     * @return array<string, \Closure>
     */
    public function nextAppointmentEagerLoad(): array
    {
        return [
            'appointments' => fn ($query) => $query->with(['client', 'doctor'])
                ->where('status', 'scheduled')
                ->whereDate('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(1),
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Clinical/ClientQueryServiceTest.php`
Expected: PASS — 11 tests (5 doctor-scoping data-provider cases + 5 non-doctor-scoping
data-provider cases + 1 name/phone filter test)

- [ ] **Step 3: Commit**

```bash
git add app/Services/Clinical/ClientQueryService.php tests/Unit/Services/Clinical/ClientQueryServiceTest.php
git commit -m "feat: add ClientQueryService, extracted client list scoping shared across specialties"
```

## Task 3: Refactor `Api\ClientController::index()` to delegate (behavior-preserving)

**Files:**
- Modify: `app/Http/Controllers/Api/ClientController.php:1-45`

- [ ] **Step 1: Replace the controller's constructor and `index()`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientListResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Specialty;
use App\Services\Clinical\ClientQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function __construct(
        protected ClientQueryService $clientQuery,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(IndexClientRequest $request)
    {
        $clients = $this->clientQuery->list(
            $request->user(),
            $request->filled('specialty') ? $request->string('specialty')->value() : null,
            $request->validated()
        );

        $resource = ClientListResource::collection($clients);

        return $this->success(
            $request->has('per_page') ? $resource->response()->getData(true) : $resource
        );
    }
```

`destroy()` is untouched. In `store()`, `show()`, and `update()`, replace the three remaining
`'appointments' => fn ($query) => ...` inline closures with
`$this->clientQuery->nextAppointmentEagerLoad()` each, so the eager-load definition exists in
exactly one place — no other behavior in these three methods changes:

```php
    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        $specialtyId = $data['specialty_id'] ?? null;
        unset($data['specialty_id']);

        $actingUser = $request->user();

        $client = Client::create([
            ...$data,
            'client_code' => $data['client_code'] ?? 'CL-'.strtoupper(Str::random(8)),
            'created_by' => $actingUser->id,
            'updated_by' => $actingUser->id,
            'status' => $data['status'] ?? 'new',
        ]);

        if ($actingUser->is_doctor) {
            $this->enrollment->ensureEnrolled($client, $actingUser);
        } elseif ($specialtyId && $specialty = Specialty::find($specialtyId)) {
            $this->enrollment->ensureEnrolledForSpecialty($client, $specialty, $actingUser);
        }

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client created successfully.', 201);
    }

    public function show(Client $client)
    {
        $client->load([
            ...$this->clientQuery->nextAppointmentEagerLoad(),
            'treatmentRecord',
        ]);

        return $this->success(ClientResource::make($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return $this->success(null, 'Client deleted successfully.');
    }
}
```

- [ ] **Step 2: Run the full existing suite to confirm zero behavior change**

Run: `php artisan test`
Expected: PASS — 421 passed (same count as before this plan; this is a pure refactor)

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/ClientController.php
git commit -m "refactor: delegate ClientController to ClientQueryService, no behavior change"
```

## Task 4: `AppointmentQueryService` — failing test first

**Files:**
- Test: `tests/Unit/Services/Clinical/AppointmentQueryServiceTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Clinical\AppointmentQueryService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public static function specialtyKeys(): array
    {
        return [
            'dental' => [Specialty::DENTAL],
            'gynecology' => [Specialty::GYNECOLOGY],
            'internal_medicine' => [Specialty::INTERNAL_MEDICINE],
            'orthopedics' => [Specialty::ORTHOPEDICS],
            'cosmetic' => [Specialty::COSMETIC],
        ];
    }

    private function makeAppointment(Company $company, User $doctor, string $clientName): Appointment
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $clientName,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);

        return Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
        ]);
    }

    #[DataProvider('specialtyKeys')]
    public function test_specialty_filter_only_returns_appointments_with_a_doctor_of_that_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();
        $matchingDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $otherSpecialty->id]);

        $this->makeAppointment($company, $matchingDoctor, 'Matching Patient');
        $this->makeAppointment($company, $otherDoctor, 'Other Patient');

        $result = app(AppointmentQueryService::class)->list(['specialty' => $specialtyKey]);

        $this->assertCount(1, $result->items());
        $this->assertSame('Matching Patient', $result->items()[0]->client->name);
    }

    public function test_doctor_id_filter_is_applied(): void
    {
        $company = Company::factory()->create();
        $doctorA = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $doctorB = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $this->makeAppointment($company, $doctorA, 'A Patient');
        $this->makeAppointment($company, $doctorB, 'B Patient');

        $result = app(AppointmentQueryService::class)->list(['doctor_id' => $doctorA->id]);

        $this->assertCount(1, $result->items());
        $this->assertSame('A Patient', $result->items()[0]->client->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Clinical/AppointmentQueryServiceTest.php`
Expected: FAIL — `Class "App\Services\Clinical\AppointmentQueryService" not found`

## Task 5: Implement `AppointmentQueryService`

**Files:**
- Create: `app/Services/Clinical/AppointmentQueryService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services\Clinical;

use App\Models\Appointment;
use App\Models\Specialty;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The one place Appointment list-query scoping lives, shared by dental's own
 * AppointmentController and every per-specialty
 * Api\{Specialty}\AppointmentController -- see
 * docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * Behavior-preserving extraction of what used to be inline in
 * Api\AppointmentController::index().
 */
class AppointmentQueryService
{
    /**
     * @param  array{doctor_id?: ?int, specialty?: ?string, branch_id?: ?int, client_id?: ?int,
     *                status?: ?string, date_from?: ?string, date_to?: ?string, date?: ?string,
     *                per_page?: ?int}  $filters
     */
    public function list(array $filters): Paginator
    {
        return Appointment::query()
            ->with(['client', 'doctor'])
            ->when($filters['doctor_id'] ?? null, fn ($query) => $query->where('doctor_id', $filters['doctor_id']))
            ->when($filters['specialty'] ?? null, function ($query) use ($filters) {
                $specialtyId = Specialty::query()->where('key', $filters['specialty'])->value('id');
                $query->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId));
            })
            ->when($filters['branch_id'] ?? null, fn ($query) => $query->whereHas('client', fn ($cq) => $cq->where('branch_id', $filters['branch_id'])))
            ->when($filters['client_id'] ?? null, fn ($query) => $query->where('client_id', $filters['client_id']))
            ->when($filters['status'] ?? null, fn ($query) => $query->where('status', $filters['status']))
            ->when(
                ($filters['date_from'] ?? null) && ($filters['date_to'] ?? null),
                fn ($query) => $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]),
                fn ($query) => $query->when($filters['date'] ?? null, fn ($q) => $q->whereDate('date', $filters['date']))
            )
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Clinical/AppointmentQueryServiceTest.php`
Expected: PASS — 6 tests (5 data-provider cases + 1 doctor_id test)

- [ ] **Step 3: Commit**

```bash
git add app/Services/Clinical/AppointmentQueryService.php tests/Unit/Services/Clinical/AppointmentQueryServiceTest.php
git commit -m "feat: add AppointmentQueryService, extracted appointment list scoping"
```

## Task 6: Refactor `Api\AppointmentController::index()` to delegate

**Files:**
- Modify: `app/Http/Controllers/Api/AppointmentController.php:20-50`

- [ ] **Step 1: Update the constructor and `index()`**

```php
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected AppointmentQueryService $appointmentQuery,
    ) {}

    public function index(IndexAppointmentRequest $request)
    {
        $appointments = $this->appointmentQuery->list($request->validated());

        return $this->success(AppointmentResource::collection($appointments));
    }
```

Add `use App\Services\Clinical\AppointmentQueryService;` to the imports. Leave `store()`,
`show()`, `update()`, `destroy()`, and `assertClientRules()` exactly as they are.

- [ ] **Step 2: Run the full existing suite to confirm zero behavior change**

Run: `php artisan test`
Expected: PASS — 421 passed

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/AppointmentController.php
git commit -m "refactor: delegate AppointmentController to AppointmentQueryService, no behavior change"
```

## Task 7: `DashboardStatsService` — failing test first

**Files:**
- Test: `tests/Unit/Services/Clinical/DashboardStatsServiceTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Clinical;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Clinical\DashboardStatsService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public static function specialtyKeys(): array
    {
        return [
            'dental' => [Specialty::DENTAL],
            'gynecology' => [Specialty::GYNECOLOGY],
            'internal_medicine' => [Specialty::INTERNAL_MEDICINE],
            'orthopedics' => [Specialty::ORTHOPEDICS],
            'cosmetic' => [Specialty::COSMETIC],
        ];
    }

    #[DataProvider('specialtyKeys')]
    public function test_appointment_totals_are_scoped_to_the_requested_specialty(string $specialtyKey): void
    {
        $company = Company::factory()->create();
        $specialty = Specialty::query()->where('key', $specialtyKey)->firstOrFail();
        $otherSpecialty = Specialty::query()->where('key', '!=', $specialtyKey)->firstOrFail();
        $matchingDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $specialty->id]);
        $otherDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $otherSpecialty->id]);

        foreach ([$matchingDoctor, $otherDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'male',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        $stats = app(DashboardStatsService::class)->stats(
            dateFrom: now()->toDateString(),
            dateTo: now()->toDateString(),
            doctorId: null,
            branchId: null,
            specialtyKey: $specialtyKey,
        );

        $this->assertSame(1, $stats['appointments']['total']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Clinical/DashboardStatsServiceTest.php`
Expected: FAIL — `Class "App\Services\Clinical\DashboardStatsService" not found`

## Task 8: Implement `DashboardStatsService`

**Files:**
- Create: `app/Services/Clinical/DashboardStatsService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services\Clinical;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Specialty;

/**
 * The one place dashboard-stats query scoping lives, shared by dental's own
 * DashboardController and every per-specialty
 * Api\{Specialty}\DashboardController -- see
 * docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 * Behavior-preserving extraction of what used to be inline in
 * Api\DashboardController::stats().
 */
class DashboardStatsService
{
    public function stats(string $dateFrom, string $dateTo, ?int $doctorId, ?int $branchId, ?string $specialtyKey): array
    {
        $specialtyId = $specialtyKey
            ? Specialty::query()->where('key', $specialtyKey)->value('id')
            : null;

        // whereDate() (not whereBetween on the raw column) because MySQL's DATE
        // column type silently truncates any time component on insert, but a
        // bare string BETWEEN comparison on a same-day range (date_from ===
        // date_to, e.g. the dashboard's "Today" filter) would otherwise be
        // sensitive to that -- whereDate() normalizes the column with SQL's
        // DATE() function first, so it compares correctly regardless of
        // whatever the underlying storage format actually is.
        $apptBase = Appointment::query()
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->where('type', '!=', 'unavailable')
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($specialtyId, fn ($q) => $q->whereHas('doctor', fn ($dq) => $dq->where('specialty_id', $specialtyId)))
            ->when($branchId, fn ($q) => $q->whereHas('client', fn ($cq) => $cq->where('branch_id', $branchId)));

        $total = (clone $apptBase)->count();
        $byStatus = (clone $apptBase)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->getRawOriginal('status') ?? $row->status?->value ?? (string) $row->status) => (int) $row->cnt,
            ])
            ->toArray();

        $statusKeys = ['scheduled', 'completed', 'cancelled', 'no_show'];
        $appointmentsByStatus = array_combine(
            $statusKeys,
            array_map(fn ($k) => (int) ($byStatus[$k] ?? 0), $statusKeys)
        );

        $payBase = Payment::query()
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateTo)
            ->when($doctorId, function ($q) use ($doctorId) {
                $q->whereHas('visit', function ($vq) use ($doctorId) {
                    $vq->where('doctor_id', $doctorId);
                });
            })
            ->when($specialtyId, function ($q) use ($specialtyId) {
                $q->whereHas('visit.doctor', function ($dq) use ($specialtyId) {
                    $dq->where('specialty_id', $specialtyId);
                });
            })
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('client', function ($cq) use ($branchId) {
                    $cq->where('branch_id', $branchId);
                });
            });

        $incomeTotal = (float) (clone $payBase)->sum('amount');

        $byMethod = (clone $payBase)
            ->selectRaw('payment_method, sum(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->getRawOriginal('payment_method') ?? $row->payment_method?->value ?? (string) $row->payment_method) => (float) $row->total,
            ])
            ->toArray();

        $byDay = (clone $payBase)
            ->selectRaw('DATE(payment_date) as date, sum(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'amount' => (float) $row->amount])
            ->values()
            ->toArray();

        return [
            'appointments' => [
                'total' => $total,
                'by_status' => $appointmentsByStatus,
            ],
            'income' => [
                'total' => $incomeTotal,
                'by_method' => $byMethod,
                'by_day' => $byDay,
            ],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Clinical/DashboardStatsServiceTest.php`
Expected: PASS — 5 tests (one per specialty key)

- [ ] **Step 3: Commit**

```bash
git add app/Services/Clinical/DashboardStatsService.php tests/Unit/Services/Clinical/DashboardStatsServiceTest.php
git commit -m "feat: add DashboardStatsService, extracted dashboard stats scoping"
```

## Task 9: Refactor `Api\DashboardController::stats()` to delegate

**Files:**
- Modify: `app/Http/Controllers/Api/DashboardController.php` (entire file)

- [ ] **Step 1: Replace the whole file**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Clinical\DashboardStatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardStatsService $dashboardStats) {}

    public function stats(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'specialty' => ['nullable', 'string', 'exists:specialties,key'],
        ]);

        $stats = $this->dashboardStats->stats(
            dateFrom: $request->date_from,
            dateTo: $request->date_to,
            doctorId: $request->doctor_id,
            branchId: $request->branch_id,
            specialtyKey: $request->filled('specialty') ? $request->string('specialty')->value() : null,
        );

        return $this->success($stats);
    }
}
```

- [ ] **Step 2: Run the full existing suite to confirm zero behavior change**

Run: `php artisan test`
Expected: PASS — 421 passed

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/DashboardController.php
git commit -m "refactor: delegate DashboardController to DashboardStatsService, no behavior change"
```

## Task 10: Gynevaria's thin `ClientController`

**Files:**
- Create: `app/Http/Controllers/Api/Gynecology/ClientController.php`
- Test: `tests/Feature/Gynecology/ClientControllerTest.php` (create)

- [ ] **Step 1: Write the failing route test**

```php
<?php

namespace Tests\Feature\Gynecology;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ClientSpecialtyEnrollmentService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function makeClient(Company $company, string $name): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_index_only_returns_gynecology_patients_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynPatient = $this->makeClient($company, 'Gyn Patient');
        $dentalPatient = $this->makeClient($company, 'Dental Patient');
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($gynPatient, $gynecology, $manager);
        app(ClientSpecialtyEnrollmentService::class)->ensureEnrolledForSpecialty($dentalPatient, $dental, $manager);

        Sanctum::actingAs($manager);

        // Deliberately NOT passing ?specialty=gynecology -- the route itself
        // must force it.
        $response = $this->getJson('/api/gynecology/clients');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Gyn Patient'));
        $this->assertFalse($names->contains('Dental Patient'));
    }

    public function test_store_enrolls_the_new_patient_as_gynecology_without_a_specialty_id_in_the_payload(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/gynecology/clients', [
            'name' => 'New Gyn Patient',
            'phone' => '+15551234567',
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $client = Client::where('name', 'New Gyn Patient')->firstOrFail();
        $this->assertDatabaseHas('client_specialty_records', [
            'client_id' => $client->id,
            'specialty_id' => Specialty::query()->where('key', Specialty::GYNECOLOGY)->value('id'),
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Gynecology/ClientControllerTest.php`
Expected: FAIL — 404 Not Found (route doesn't exist yet)

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientListResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Specialty;
use App\Services\Clinical\ClientQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use Illuminate\Support\Str;

/**
 * Gynevaria's own clinical Client endpoints. Thin delegation to the same
 * ClientQueryService dental's Api\ClientController uses, with the specialty
 * hardcoded to gynecology rather than read from a query param -- the URL
 * namespace itself declares intent, so a caller can't override it. See
 * docs/superpowers/specs/2026-08-17-doctovaria-per-specialty-separation-design.md.
 */
class ClientController extends Controller
{
    public function __construct(
        protected ClientQueryService $clientQuery,
        protected ClientSpecialtyEnrollmentService $enrollment,
    ) {}

    public function index(IndexClientRequest $request)
    {
        $clients = $this->clientQuery->list($request->user(), Specialty::GYNECOLOGY, $request->validated());

        $resource = ClientListResource::collection($clients);

        return $this->success(
            $request->has('per_page') ? $resource->response()->getData(true) : $resource
        );
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        unset($data['specialty_id']);

        $actingUser = $request->user();

        $client = Client::create([
            ...$data,
            'client_code' => $data['client_code'] ?? 'CL-'.strtoupper(Str::random(8)),
            'created_by' => $actingUser->id,
            'updated_by' => $actingUser->id,
            'status' => $data['status'] ?? 'new',
        ]);

        if ($actingUser->is_doctor) {
            $this->enrollment->ensureEnrolled($client, $actingUser);
        } else {
            $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
            $this->enrollment->ensureEnrolledForSpecialty($client, $gynecology, $actingUser);
        }

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client created successfully.', 201);
    }

    public function show(Client $client)
    {
        $client->load([
            ...$this->clientQuery->nextAppointmentEagerLoad(),
            'treatmentRecord',
        ]);

        return $this->success(ClientResource::make($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(ClientResource::make($client->load($this->clientQuery->nextAppointmentEagerLoad())), 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return $this->success(null, 'Client deleted successfully.');
    }
}
```

- [ ] **Step 4: Create the route file**

Create `routes/api/gynecology.php`:

```php
<?php

use App\Http\Controllers\Api\Gynecology\AppointmentController;
use App\Http\Controllers\Api\Gynecology\ClientController;
use App\Http\Controllers\Api\Gynecology\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('gynecology')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('appointments', AppointmentController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
});
```

Add this line at the very end of `routes/api.php` (after the existing closing `});` on the last
line):

```php
require __DIR__.'/api/gynecology.php';
```

The route file's `use App\Http\Controllers\Api\Gynecology\AppointmentController;` and
`use ...\DashboardController;` lines are safe even though those two classes don't exist until
Tasks 11-12 — `SomeClass::class` resolves to a plain string at compile time and does not trigger
autoloading, so `Route::apiResource('appointments', AppointmentController::class)` only fails if
that specific route is actually dispatched, not at boot.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Gynecology/ClientControllerTest.php`
Expected: PASS — 2 tests (only the Client routes are exercised, so Tasks 11-12 aren't required yet)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Gynecology/ClientController.php tests/Feature/Gynecology/ClientControllerTest.php routes/api/gynecology.php routes/api.php
git commit -m "feat: add Gynevaria's own Client endpoints under /api/gynecology"
```

## Task 11: Gynevaria's thin `AppointmentController`

**Files:**
- Create: `app/Http/Controllers/Api/Gynecology/AppointmentController.php`
- Test: `tests/Feature/Gynecology/AppointmentControllerTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Gynecology;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_index_only_returns_appointments_with_a_gynecology_doctor_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);

        foreach ([$gynDoctor, $dentalDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'female',
                'status' => 'new',
            ]);
            \App\Models\Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->addDay()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/gynecology/appointments');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('client.name');
        $this->assertTrue($names->contains('Patient of '.$gynDoctor->id));
        $this->assertFalse($names->contains('Patient of '.$dentalDoctor->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Gynecology/AppointmentControllerTest.php`
Expected: FAIL — 404 Not Found (route resolves to a controller class that doesn't exist yet)

- [ ] **Step 3: Write the controller**

This is a straight copy of `app/Http/Controllers/Api/AppointmentController.php` with the
constructor's `AppointmentQueryService` dependency added (dental's own controller doesn't need it
directly since Task 6 already wired that one) and `index()` replaced:

```php
<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Enums\AppointmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Services\AppointmentConflictService;
use App\Services\Clinical\AppointmentQueryService;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\TreatmentChargeService;
use Illuminate\Validation\ValidationException;

/**
 * Gynevaria's own clinical Appointment endpoints. See
 * app/Http/Controllers/Api/Gynecology/ClientController.php's docblock.
 */
class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentConflictService $conflicts,
        protected TreatmentChargeService $treatmentCharges,
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected AppointmentQueryService $appointmentQuery,
    ) {}

    public function index(IndexAppointmentRequest $request)
    {
        $appointments = $this->appointmentQuery->list([
            ...$request->validated(),
            'specialty' => 'gynecology',
        ]);

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $chargeItems = $data['charge_items'] ?? [];
        unset($data['charge_items']);

        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], (int) $data['duration_minutes']);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], (int) $data['duration_minutes']);

        $appointment = Appointment::create([
            ...$data,
            'status' => $data['status'] ?? 'scheduled',
            'client_id' => $data['type'] === AppointmentType::Unavailable->value ? null : $data['client_id'],
            'end_time' => $this->conflicts->calculateEndTime($data['start_time'], (int) $data['duration_minutes']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($appointment->client_id) {
            $this->treatmentCharges->syncItems($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);
            $this->enrollment->ensureEnrolled($appointment->client, $doctor);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment created successfully.', 201);
    }

    public function show(Appointment $appointment)
    {
        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $validated = $request->validated();
        $chargeItemsProvided = array_key_exists('charge_items', $validated);
        $chargeItems = $validated['charge_items'] ?? [];
        unset($validated['charge_items']);

        $data = [
            ...$appointment->only(['client_id', 'doctor_id', 'type', 'status', 'date', 'start_time', 'duration_minutes', 'notes']),
            ...$validated,
        ];

        $doctor = User::findOrFail($data['doctor_id']);
        $this->assertClientRules($data);
        $this->conflicts->assertWithinSchedule($doctor, $data['date'], $data['start_time'], (int) $data['duration_minutes']);
        $this->conflicts->assertNoConflict($doctor->id, $data['date'], $data['start_time'], (int) $data['duration_minutes'], $appointment->id);

        $appointment->update([
            ...$validated,
            'client_id' => $data['type'] === AppointmentType::Unavailable->value ? null : $data['client_id'],
            'end_time' => $this->conflicts->calculateEndTime($data['start_time'], (int) $data['duration_minutes']),
            'updated_by' => $request->user()->id,
        ]);

        if ($chargeItemsProvided && $appointment->client_id) {
            $this->treatmentCharges->syncItems($appointment->client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);
        }

        return $this->success(AppointmentResource::make($appointment->load(['client', 'doctor'])), 'Appointment updated successfully.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->treatmentCharges->deleteAllForAppointment($appointment->id, $appointment->client?->company_id);
        $appointment->delete();

        return $this->success(null, 'Appointment deleted successfully.');
    }

    protected function assertClientRules(array $data): void
    {
        if (($data['type'] ?? null) === AppointmentType::Booked->value && empty($data['client_id'])) {
            throw ValidationException::withMessages([
                'client_id' => ['The client field is required for booked appointments.'],
            ]);
        }

        if (($data['type'] ?? null) === AppointmentType::Unavailable->value && ! empty($data['client_id'])) {
            throw ValidationException::withMessages([
                'client_id' => ['The client field must be null for unavailable appointments.'],
            ]);
        }
    }
}
```

Note `index()` forces `'specialty' => 'gynecology'` into the filters array, overwriting whatever
(if anything) was in the validated request — same "the URL declares intent, callers can't override
it" rule as `ClientController::index()`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Gynecology/AppointmentControllerTest.php`
Expected: PASS — 1 test

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Gynecology/AppointmentController.php tests/Feature/Gynecology/AppointmentControllerTest.php
git commit -m "feat: add Gynevaria's own Appointment endpoints under /api/gynecology"
```

## Task 12: Gynevaria's thin `DashboardController`

**Files:**
- Create: `app/Http/Controllers/Api/Gynecology/DashboardController.php`
- Test: `tests/Feature/Gynecology/DashboardControllerTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Gynecology;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    public function test_stats_only_counts_gynecology_appointments_even_without_a_specialty_query_param(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $gynecology->id]);
        $dentalDoctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true, 'specialty_id' => $dental->id]);

        foreach ([$gynDoctor, $dentalDoctor] as $doctor) {
            $client = Client::create([
                'company_id' => $company->id,
                'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
                'name' => 'Patient of '.$doctor->id,
                'phone' => fake()->unique()->e164PhoneNumber(),
                'gender' => 'female',
                'status' => 'new',
            ]);
            Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'type' => 'booked',
                'status' => 'scheduled',
                'date' => now()->toDateString(),
                'start_time' => '10:00:00',
                'duration_minutes' => 30,
            ]);
        }

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/gynecology/dashboard/stats?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('data.appointments.total', 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Gynecology/DashboardControllerTest.php`
Expected: FAIL — 404 Not Found

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api\Gynecology;

use App\Http\Controllers\Controller;
use App\Services\Clinical\DashboardStatsService;
use Illuminate\Http\Request;

/**
 * Gynevaria's own clinical Dashboard endpoint. See
 * app/Http/Controllers/Api/Gynecology/ClientController.php's docblock.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardStatsService $dashboardStats) {}

    public function stats(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $stats = $this->dashboardStats->stats(
            dateFrom: $request->date_from,
            dateTo: $request->date_to,
            doctorId: $request->doctor_id,
            branchId: $request->branch_id,
            specialtyKey: 'gynecology',
        );

        return $this->success($stats);
    }
}
```

- [ ] **Step 4: Run all the new Gynevaria feature tests to verify they pass**

Run: `php artisan test tests/Feature/Gynecology`
Expected: PASS — 4 tests (2 in ClientControllerTest, 1 in AppointmentControllerTest, 1 in DashboardControllerTest)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Gynecology/DashboardController.php tests/Feature/Gynecology/DashboardControllerTest.php
git commit -m "feat: add Gynevaria's own Dashboard endpoint under /api/gynecology"
```

## Task 13: Full regression pass + Pint

**Files:** none new — verification only

- [ ] **Step 1: Run the entire suite**

Run: `php artisan test`
Expected: PASS — 421 + 11 (ClientQueryServiceTest) + 6 (AppointmentQueryServiceTest) + 5
(DashboardStatsServiceTest) + 4 (Gynevaria feature tests) = 447 passed

- [ ] **Step 2: Run Pint on everything touched this plan**

Run: `./vendor/bin/pint --dirty`
Expected: all files pass or get auto-fixed; re-run `php artisan test` if Pint changed any file's
logic-relevant whitespace is unlikely but confirm green either way

- [ ] **Step 3: Commit if Pint made changes**

```bash
git add -u
git commit -m "style: pint"
```

## Task 14: Deploy and verify in production

**Files:** none — deployment/verification only

- [ ] **Step 1: Confirm WinSCP sync has caught up, then re-trigger migrate.php**

No new migrations in this plan (only new PHP classes + a route file), but hit
`https://dentavaria.technovaria.com/migrate.php` anyway to force an OPcache/config-cache reset
(this host runs `opcache.validate_timestamps=0`, per this project's established deploy pattern).

- [ ] **Step 2: Verify production health**

Confirm `https://dentavaria.technovaria.com/` returns 200 twice in a row (this project's
established post-deploy check).

- [ ] **Step 3: Verify the new endpoint directly against production**

Using the seeded demo project-admin/doctor credentials (real `.env` has `INFOBIP_ENABLED=false` +
fixed OTP, so this doesn't need SMS access), hit `GET /api/gynecology/clients` with a valid
Sanctum token and confirm a 200 response (empty data array is fine — no real gynecology patients
exist in production yet).
