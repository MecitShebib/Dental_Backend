# AI Treatment Plan Charge (Backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a doctor record a fee against a client immediately after confirming an AI-generated treatment plan, and have that fee add to (not replace) the client's existing odontogram-derived total owed.

**Architecture:** A new `ai_treatment_plan_charges` table + `AiTreatmentPlanCharge` model, a `hasMany` relation on `Client`, one new doctor-only endpoint (`POST /clients/{client}/ai-treatment-plan/charge`) that creates a charge row, and a one-line change to `ClientFinancialSummaryService::summary()` so the combined total (odontogram + all charges) flows through everywhere that already reads `total_services_amount`.

**Tech Stack:** Laravel 12, Sanctum, SQLite (test/dev), PHPUnit feature + unit tests.

Full design: `docs/superpowers/specs/2026-07-08-ai-treatment-plan-charge-design.md`.

---

### Task 1: Migration + `AiTreatmentPlanCharge` model

**Files:**
- Create: `database/migrations/2026_07_08_000000_create_ai_treatment_plan_charges_table.php`
- Create: `app/Models/AiTreatmentPlanCharge.php`
- Modify: `app/Models/Client.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_treatment_plan_charges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_treatment_plan_charges');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: ..._create_ai_treatment_plan_charges_table` then `Migrated:` with no errors.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTreatmentPlanCharge extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'amount',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 4: Add the relation to `Client`**

In `app/Models/Client.php`, add this method next to the existing `payments()` relation (after line 60):

```php
    public function aiTreatmentPlanCharges(): HasMany
    {
        return $this->hasMany(AiTreatmentPlanCharge::class);
    }
```

- [ ] **Step 5: Verify in tinker**

Run: `php artisan tinker --execute="echo App\Models\AiTreatmentPlanCharge::class . ' OK';"`
Expected: `App\Models\AiTreatmentPlanCharge OK` with no errors (confirms the class autoloads and the model file has no syntax errors).

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/AiTreatmentPlanCharge.php app/Models/Client.php
git commit -m "feat: add ai_treatment_plan_charges table and model"
```

---

### Task 2: `ClientFinancialSummaryService` — charges add to the total

**Files:**
- Modify: `app/Services/ClientFinancialSummaryService.php`
- Test: `tests/Unit/Services/ClientFinancialSummaryServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Services\ClientFinancialSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFinancialSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(): Client
    {
        return Client::create([
            'client_code' => 'CL-9001',
            'name' => 'Test Client',
            'phone' => '+963900009001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_summary_is_zero_with_no_treatment_record_charges_or_payments(): void
    {
        $client = $this->makeClient();

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(0.0, $summary['total_services_amount']);
        $this->assertSame(0.0, $summary['total_paid_amount']);
        $this->assertSame(0.0, $summary['remaining_amount']);
    }

    public function test_ai_plan_charges_add_on_top_of_the_treatment_record_amount(): void
    {
        $client = $this->makeClient();
        $client->treatmentRecord()->create(['total_services_amount' => 100]);
        $client->aiTreatmentPlanCharges()->create(['amount' => 50]);
        $client->aiTreatmentPlanCharges()->create(['amount' => 25]);

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(175.0, $summary['total_services_amount']);
    }

    public function test_payments_deduct_from_the_combined_total(): void
    {
        $client = $this->makeClient();
        $client->treatmentRecord()->create(['total_services_amount' => 100]);
        $client->aiTreatmentPlanCharges()->create(['amount' => 50]);
        $client->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => 60,
            'payment_method' => 'cash',
        ]);

        $summary = app(ClientFinancialSummaryService::class)->summary($client);

        $this->assertSame(150.0, $summary['total_services_amount']);
        $this->assertSame(60.0, $summary['total_paid_amount']);
        $this->assertSame(90.0, $summary['remaining_amount']);
    }
}
```

- [ ] **Step 2: Run the tests to verify the second and third fail**

Run: `php artisan test tests/Unit/Services/ClientFinancialSummaryServiceTest.php`
Expected: `test_summary_is_zero_with_no_treatment_record_charges_or_payments` PASSES (current code already
handles the zero case); `test_ai_plan_charges_add_on_top_of_the_treatment_record_amount` FAILS,
asserting `100.0` (treatment record only) instead of `175.0`; `test_payments_deduct_from_the_combined_total`
FAILS similarly.

- [ ] **Step 3: Update the service**

In `app/Services/ClientFinancialSummaryService.php`, replace the body of `summary()`:

```php
<?php

namespace App\Services;

use App\Models\Client;

class ClientFinancialSummaryService
{
    public function summary(Client $client): array
    {
        $totalServices = (float) optional($client->treatmentRecord)->total_services_amount
            + (float) $client->aiTreatmentPlanCharges()->sum('amount');
        $totalPaid = (float) $client->payments()->sum('amount');

        return [
            'total_services_amount' => round($totalServices, 2),
            'total_paid_amount' => round($totalPaid, 2),
            'remaining_amount' => round($totalServices - $totalPaid, 2),
        ];
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/ClientFinancialSummaryServiceTest.php`
Expected: all 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ClientFinancialSummaryService.php tests/Unit/Services/ClientFinancialSummaryServiceTest.php
git commit -m "feat: include ai treatment plan charges in client financial summary"
```

---

### Task 3: `POST /clients/{client}/ai-treatment-plan/charge` endpoint

**Files:**
- Create: `app/Http/Requests/AiTreatmentPlan/AddAiTreatmentPlanChargeRequest.php`
- Modify: `app/Http/Controllers/Api/AiTreatmentPlanController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AiTreatmentPlan/AddAiTreatmentPlanChargeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddAiTreatmentPlanChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(string $code = 'CL-9101'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Rama',
            'phone' => '+963900009101',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_a_doctor_can_record_a_charge(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 150.5,
            'description' => 'Root canal, 2 sessions',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('ai_treatment_plan_charges', [
            'client_id' => $client->id,
            'amount' => 150.5,
            'description' => 'Root canal, 2 sessions',
            'created_by' => $doctor->id,
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.financial_summary.total_services_amount', 150.5);
    }

    public function test_description_is_optional(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9102');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 75,
        ])->assertCreated();

        $this->assertDatabaseHas('ai_treatment_plan_charges', [
            'client_id' => $client->id,
            'amount' => 75,
            'description' => null,
        ]);
    }

    public function test_a_non_doctor_cannot_record_a_charge(): void
    {
        $nonDoctor = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($nonDoctor);
        $client = $this->makeClient('CL-9103');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('doctor');

        $this->assertDatabaseCount('ai_treatment_plan_charges', 0);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-9104');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [
            'amount' => -10,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/charge", [])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('ai_treatment_plan_charges', 0);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/AiTreatmentPlan/AddAiTreatmentPlanChargeTest.php`
Expected: FAIL — route `clients/{client}/ai-treatment-plan/charge` does not exist (404s), since
nothing has been wired yet.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;

class AddAiTreatmentPlanChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/Api/AiTreatmentPlanController.php`:

Add the import at the top, alongside the existing `use` statements:

```php
use App\Http\Requests\AiTreatmentPlan\AddAiTreatmentPlanChargeRequest;
use App\Services\ClientFinancialSummaryService;
```

Add the new method after `confirm()` (after line 49, before `protected function assertIsDoctor`):

```php
    public function addCharge(AddAiTreatmentPlanChargeRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);

        $client->aiTreatmentPlanCharges()->create([
            'amount' => $request->validated('amount'),
            'description' => $request->validated('description'),
            'created_by' => $doctor->id,
        ]);

        return $this->success(
            app(ClientFinancialSummaryService::class)->summary($client),
            'Charge recorded successfully.',
            201,
        );
    }
```

- [ ] **Step 5: Add the route**

In `routes/api.php`, add this line directly after line 57
(`Route::post('clients/{client}/ai-treatment-plan/confirm', ...)`):

```php
    Route::post('clients/{client}/ai-treatment-plan/charge', [AiTreatmentPlanController::class, 'addCharge']);
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/AiTreatmentPlan/AddAiTreatmentPlanChargeTest.php`
Expected: all 4 tests PASS.

- [ ] **Step 7: Run the full backend test suite**

Run: `composer run test`
Expected: all tests PASS, including the pre-existing `ConfirmAiTreatmentPlanTest` and
`PreviewAiTreatmentPlanTest` suites (unaffected by this change) and `AiTokenUsageServiceTest`
(unrelated, confirms no regressions elsewhere).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/AiTreatmentPlan/AddAiTreatmentPlanChargeRequest.php \
        app/Http/Controllers/Api/AiTreatmentPlanController.php \
        routes/api.php \
        tests/Feature/AiTreatmentPlan/AddAiTreatmentPlanChargeTest.php
git commit -m "feat: add endpoint to record a fee after confirming an AI treatment plan"
```

---

## Plan self-review notes

- **Spec coverage**: §3 data model → Task 1. §4 service change → Task 2. §5 endpoint → Task 3.
  §6 error handling → covered by Task 3's tests (non-doctor, invalid amount). §7 testing → each
  task's own test file, plus Task 3 Step 7 runs the full suite. §8 open items (migration
  filename, precondition check) are implementation-time decisions already resolved here (a fixed
  timestamp was picked; no precondition check was added, matching the spec's explicit decision).
- **Type consistency**: `amount` is `decimal(12,2)` in the migration, cast `'amount' =>
  'decimal:2'` on the model, validated `numeric|min:0.01` in the request — consistent through all
  three layers. The relation method name `aiTreatmentPlanCharges()` is used identically in
  `Client.php`, the service, and both test files.
