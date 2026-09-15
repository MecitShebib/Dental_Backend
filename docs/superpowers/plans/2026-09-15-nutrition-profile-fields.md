# Nutrition Profile Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a static nutrition profile (height, dietary type, allergies, chronic conditions, activity level, goal, target weight, etc.) per client for the Nutrition (Dietavaria) specialty, per section 3 of `docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md`.

**Architecture:** A new `nutrition_client_profiles` table, one row per `client_id` (created on demand via `firstOrCreate`, mirroring the existing `TreatmentRecord` pattern), exposed through a dedicated `GET`/`PUT` endpoint under `routes/api/nutrition.php` (`ClientProfileController`), reusing the existing `AuthorizesOwnDoctorRecords` ownership gate the other Nutrition client endpoints already use. On the frontend, this is a self-contained addition following the same "own local state + direct `api.nutrition.*` calls" pattern `NutritionCarePlanModal.jsx` already uses (not routed through the giant shared `AppStateApiContext.jsx`, and not touching the shared `ClientFormFields.jsx` component every other specialty also uses) — an editable section in `NutritionClientEditPage.jsx` with its own save action, and a read-only display block in `NutritionClientDetailsPage.jsx`'s existing "Hasta Verisi" tab.

**Tech Stack:** Laravel 12 (migration, backed PHP enums, FormRequest, API Resource, Sanctum-authenticated controller), React 18 (Vite) on the frontend. Backend verification is `php artisan test` (real Feature tests, TDD); frontend verification is `npm run lint`/`npm run build` plus manual browser check, per this project's established convention (no frontend test runner exists in this repo).

---

### Task 1: Migration for `nutrition_client_profiles`

**Files:**
- Create: `C:\Users\MK\Desktop\Dental_Backend\database\migrations\2026_09_15_000002_create_nutrition_client_profiles_table.php`

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
        Schema::create('nutrition_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->string('dietary_type')->nullable();
            $table->json('allergies')->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->text('medications_affecting_diet')->nullable();
            $table->string('smoking_status')->nullable();
            $table->string('alcohol_status')->nullable();
            $table->string('activity_level')->nullable();
            $table->string('goal')->nullable();
            $table->decimal('target_weight_kg', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_client_profiles');
    }
};
```

- [ ] **Step 2: Run the migration against the local test/dev database**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan migrate`
Expected: `Migrating: 2026_09_15_000002_create_nutrition_client_profiles_table` then `Migrated:` with no errors. (Feature tests in this project use `RefreshDatabase`, which runs migrations automatically against an in-memory/test connection, but running it locally now catches typos immediately.)

- [ ] **Step 3: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add database/migrations/2026_09_15_000002_create_nutrition_client_profiles_table.php
git commit -m "$(cat <<'EOF'
feat: add nutrition_client_profiles table

One row per client_id -- static nutrition profile data (height,
dietary type, allergies, chronic conditions, activity level, goal,
target weight). Part of sub-project 2 in
docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Backed enums for the profile's categorical fields

**Files:**
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Enums\NutritionDietaryType.php`
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Enums\NutritionSubstanceUseStatus.php`
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Enums\NutritionActivityLevel.php`
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Enums\NutritionGoal.php`

This codebase's existing enums (`ClientGender`, `ClientStatus`, `AttendanceStatus`) are bare backed enums — `string`-backed, PascalCase cases, snake_case values, no `label()`/helper methods. Match that exactly. Note: smoking and alcohol status share the identical three-value shape (none/occasional/regular), so one `NutritionSubstanceUseStatus` enum is reused for both `smoking_status` and `alcohol_status` columns — avoids two byte-for-byte-identical enum classes (DRY).

- [ ] **Step 1: Create `NutritionDietaryType.php`**

```php
<?php

namespace App\Enums;

enum NutritionDietaryType: string
{
    case Omnivore = 'omnivore';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Halal = 'halal';
    case Kosher = 'kosher';
    case Other = 'other';
}
```

- [ ] **Step 2: Create `NutritionSubstanceUseStatus.php`**

```php
<?php

namespace App\Enums;

enum NutritionSubstanceUseStatus: string
{
    case None = 'none';
    case Occasional = 'occasional';
    case Regular = 'regular';
}
```

- [ ] **Step 3: Create `NutritionActivityLevel.php`**

```php
<?php

namespace App\Enums;

enum NutritionActivityLevel: string
{
    case Sedentary = 'sedentary';
    case Light = 'light';
    case Moderate = 'moderate';
    case Active = 'active';
    case VeryActive = 'very_active';
}
```

- [ ] **Step 4: Create `NutritionGoal.php`**

```php
<?php

namespace App\Enums;

enum NutritionGoal: string
{
    case WeightLoss = 'weight_loss';
    case WeightGain = 'weight_gain';
    case Maintenance = 'maintenance';
    case MuscleGain = 'muscle_gain';
    case MedicalDiet = 'medical_diet';
    case Other = 'other';
}
```

- [ ] **Step 5: Verify the files parse (PHP lint)**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php -l app/Enums/NutritionDietaryType.php && php -l app/Enums/NutritionSubstanceUseStatus.php && php -l app/Enums/NutritionActivityLevel.php && php -l app/Enums/NutritionGoal.php`
Expected: `No syntax errors detected` four times.

- [ ] **Step 6: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add app/Enums/NutritionDietaryType.php app/Enums/NutritionSubstanceUseStatus.php app/Enums/NutritionActivityLevel.php app/Enums/NutritionGoal.php
git commit -m "$(cat <<'EOF'
feat: add backed enums for the nutrition client profile

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `NutritionClientProfile` model and `Client::nutritionProfile()` relation

**Files:**
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Models\NutritionClientProfile.php`
- Modify: `C:\Users\MK\Desktop\Dental_Backend\app\Models\Client.php` (add a `nutritionProfile()` relation next to the existing `treatmentRecord()`/`aiConversation()` `HasOne` relations)

- [ ] **Step 1: Create the model**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionClientProfile extends Model
{
    use BelongsToCompanyViaClient, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'height_cm',
        'dietary_type',
        'allergies',
        'chronic_conditions',
        'medications_affecting_diet',
        'smoking_status',
        'alcohol_status',
        'activity_level',
        'goal',
        'target_weight_kg',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:1',
            'target_weight_kg' => 'decimal:1',
            'allergies' => 'array',
            'chronic_conditions' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 2: Add the relation to `Client.php`**

Find `Client.php`'s `treatmentRecord()` method:

```php
    public function treatmentRecord(): HasOne
    {
        return $this->hasOne(TreatmentRecord::class);
    }
```

Add immediately after it:

```php
    public function treatmentRecord(): HasOne
    {
        return $this->hasOne(TreatmentRecord::class);
    }

    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionClientProfile::class);
    }
```

(`HasOne` is already imported in this file, since `treatmentRecord()` and `aiConversation()` both use it — no new `use` statement needed.)

- [ ] **Step 3: Verify with Tinker**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan tinker --execute="echo (new \App\Models\Client)->nutritionProfile() instanceof \Illuminate\Database\Eloquent\Relations\HasOne ? 'OK' : 'FAIL';"`
Expected: `OK`

- [ ] **Step 4: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add app/Models/NutritionClientProfile.php app/Models/Client.php
git commit -m "$(cat <<'EOF'
feat: add NutritionClientProfile model and Client relation

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.5: Add audit trail to `NutritionClientProfile` (Auditable + created_by/updated_by)

> Added after a code-quality review of Task 3 found a real gap: this table stores KVKK-sensitive health data (allergies, chronic conditions, medications, smoking/alcohol status) but has neither the `Auditable` trait (auto-writes an `AuditLog` row on create/update/delete — see `app/Models/Concerns/Auditable.php`, built for exactly this class of model per `docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md`) nor `created_by`/`updated_by` columns, unlike the closer, more recent sibling table `PatientLabResult` (also per-client health data, also `BelongsToCompanyViaClient`) which has both. Fixed via a follow-up migration rather than editing the already-committed one.

**Files:**
- Create: `C:\Users\MK\Desktop\Dental_Backend\database\migrations\2026_09_15_000003_add_audit_columns_to_nutrition_client_profiles_table.php`
- Modify: `C:\Users\MK\Desktop\Dental_Backend\app\Models\NutritionClientProfile.php`

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
        Schema::table('nutrition_client_profiles', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_client_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
```

- [ ] **Step 2: Update the model**

Find:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionClientProfile extends Model
{
    use BelongsToCompanyViaClient, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'height_cm',
        'dietary_type',
        'allergies',
        'chronic_conditions',
        'medications_affecting_diet',
        'smoking_status',
        'alcohol_status',
        'activity_level',
        'goal',
        'target_weight_kg',
        'notes',
    ];
```

Replace with (adds the trait and the two new fillable columns; alphabetizes the trait list the way `PatientLabResult` does):

```php
<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'height_cm',
        'dietary_type',
        'allergies',
        'chronic_conditions',
        'medications_affecting_diet',
        'smoking_status',
        'alcohol_status',
        'activity_level',
        'goal',
        'target_weight_kg',
        'notes',
        'created_by',
        'updated_by',
    ];
```

- [ ] **Step 3: Run the migration and verify the columns exist**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="C:\Users\MK\Desktop\Dental_Backend\database\database.sqlite" php artisan migrate`

IMPORTANT: always use that exact `APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="C:\Users\MK\Desktop\Dental_Backend\database\database.sqlite"` prefix for every artisan command in this and all later tasks — this repo's real `.env` currently points at a production database (a known, separately-flagged issue), and running artisan commands without this override risks connecting to it (at best hanging on a confirmation prompt, at worst touching real data).

Expected: `Migrating: 2026_09_15_000003_add_audit_columns_to_nutrition_client_profiles_table` then `Migrated:`.

Then verify: `cd "C:\Users\MK\Desktop\Dental_Backend" && APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="C:\Users\MK\Desktop\Dental_Backend\database\database.sqlite" php artisan tinker --execute="print_r(Schema::getColumnListing('nutrition_client_profiles'));"`
Expected: the list now includes `created_by` and `updated_by`.

- [ ] **Step 4: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add database/migrations/2026_09_15_000003_add_audit_columns_to_nutrition_client_profiles_table.php app/Models/NutritionClientProfile.php
git commit -m "$(cat <<'EOF'
feat: add audit trail to NutritionClientProfile

Adds the Auditable trait (auto-writes an AuditLog row on
create/update/delete, per docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md)
plus created_by/updated_by columns, matching the closer sibling
table PatientLabResult -- this table holds the same class of
KVKK-sensitive health data (allergies, chronic conditions,
medications, smoking/alcohol status). Found by code review after
Task 3.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Profile endpoint (FormRequest, Resource, Controller, routes) — TDD via Feature test

**Files:**
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Http\Requests\Nutrition\UpdateNutritionClientProfileRequest.php`
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Http\Resources\NutritionClientProfileResource.php`
- Create: `C:\Users\MK\Desktop\Dental_Backend\app\Http\Controllers\Api\Nutrition\ClientProfileController.php`
- Modify: `C:\Users\MK\Desktop\Dental_Backend\routes\api\nutrition.php`
- Test: `C:\Users\MK\Desktop\Dental_Backend\tests\Feature\Nutrition\ClientProfileTest.php`

- [ ] **Step 1: Write the failing Feature test**

```php
<?php

namespace Tests\Feature\Nutrition;

use App\Models\Client;
use App\Models\ClientSpecialtyRecord;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function makeNutritionDoctorAndClient(Company $company): array
    {
        $nutrition = Specialty::query()->where('key', Specialty::NUTRITION)->firstOrFail();
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $nutrition->id,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Nutrition Profile Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'female',
            'status' => 'new',
        ]);

        ClientSpecialtyRecord::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'specialty_id' => $nutrition->id,
            'primary_doctor_id' => $doctor->id,
        ]);

        return [$doctor, $client];
    }

    public function test_show_creates_an_empty_profile_on_first_access(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/profile");

        $response->assertOk();
        $response->assertJsonPath('data.client_id', $client->id);
        $response->assertJsonPath('data.height_cm', null);
        $this->assertDatabaseHas('nutrition_client_profiles', ['client_id' => $client->id]);
    }

    public function test_update_persists_all_fields(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $payload = [
            'height_cm' => 168.5,
            'dietary_type' => 'vegetarian',
            'allergies' => ['peanuts', 'shellfish'],
            'chronic_conditions' => ['type_2_diabetes'],
            'medications_affecting_diet' => 'Metformin 500mg',
            'smoking_status' => 'none',
            'alcohol_status' => 'occasional',
            'activity_level' => 'moderate',
            'goal' => 'weight_loss',
            'target_weight_kg' => 62.0,
            'notes' => 'Prefers gluten-free where possible.',
        ];

        $response = $this->putJson("/api/nutrition/clients/{$client->id}/profile", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.height_cm', '168.5');
        $response->assertJsonPath('data.dietary_type', 'vegetarian');
        $response->assertJsonPath('data.allergies', ['peanuts', 'shellfish']);
        $response->assertJsonPath('data.goal', 'weight_loss');
        $this->assertDatabaseHas('nutrition_client_profiles', [
            'client_id' => $client->id,
            'dietary_type' => 'vegetarian',
            'activity_level' => 'moderate',
        ]);
    }

    public function test_update_rejects_an_invalid_enum_value(): void
    {
        $company = Company::factory()->create();
        [$doctor, $client] = $this->makeNutritionDoctorAndClient($company);

        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/nutrition/clients/{$client->id}/profile", [
            'dietary_type' => 'carnivore-extreme',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dietary_type']);
    }

    public function test_a_doctor_from_another_specialty_cannot_access_the_profile(): void
    {
        $company = Company::factory()->create();
        [, $client] = $this->makeNutritionDoctorAndClient($company);

        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $dentalDoctor = User::factory()->create([
            'company_id' => $company->id,
            'is_doctor' => true,
            'specialty_id' => $dental->id,
        ]);

        Sanctum::actingAs($dentalDoctor);

        $response = $this->getJson("/api/nutrition/clients/{$client->id}/profile");

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan test tests/Feature/Nutrition/ClientProfileTest.php`
Expected: FAIL — routes `/api/nutrition/clients/{client}/profile` don't exist yet (404), so `assertOk()` fails.

- [ ] **Step 3: Write the FormRequest**

```php
<?php

namespace App\Http\Requests\Nutrition;

use App\Enums\NutritionActivityLevel;
use App\Enums\NutritionDietaryType;
use App\Enums\NutritionGoal;
use App\Enums\NutritionSubstanceUseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNutritionClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'dietary_type' => ['nullable', Rule::enum(NutritionDietaryType::class)],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
            'chronic_conditions' => ['nullable', 'array'],
            'chronic_conditions.*' => ['string', 'max:255'],
            'medications_affecting_diet' => ['nullable', 'string'],
            'smoking_status' => ['nullable', Rule::enum(NutritionSubstanceUseStatus::class)],
            'alcohol_status' => ['nullable', Rule::enum(NutritionSubstanceUseStatus::class)],
            'activity_level' => ['nullable', Rule::enum(NutritionActivityLevel::class)],
            'goal' => ['nullable', Rule::enum(NutritionGoal::class)],
            'target_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Write the Resource**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->client_id,
            'height_cm' => $this->height_cm,
            'dietary_type' => $this->dietary_type,
            'allergies' => $this->allergies ?? [],
            'chronic_conditions' => $this->chronic_conditions ?? [],
            'medications_affecting_diet' => $this->medications_affecting_diet,
            'smoking_status' => $this->smoking_status,
            'alcohol_status' => $this->alcohol_status,
            'activity_level' => $this->activity_level,
            'goal' => $this->goal,
            'target_weight_kg' => $this->target_weight_kg,
            'notes' => $this->notes,
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\UpdateNutritionClientProfileRequest;
use App\Http\Resources\NutritionClientProfileResource;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientProfileController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function show(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->nutritionProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );

        return $this->success(NutritionClientProfileResource::make($profile));
    }

    public function update(UpdateNutritionClientProfileRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $profile = $client->nutritionProfile()->firstOrCreate(
            ['client_id' => $client->id],
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        );
        $profile->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(NutritionClientProfileResource::make($profile), 'Nutrition profile updated successfully.');
    }
}
```

(`created_by`/`updated_by` mirror the exact pattern `PatientLabResultController::store()`/`update()` already use — set both on first creation, `updated_by` refreshed on every update, `created_by` left untouched after that. Not exposed in `NutritionClientProfileResource`'s JSON output, matching `PatientLabResultResource`, which doesn't expose them either — they're internal audit fields, not frontend-facing.)

- [ ] **Step 6: Add the routes**

In `routes/api/nutrition.php`, find:

```php
Route::prefix('nutrition')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('nutrition.clients');
```

Add the two profile routes right after the `clients` apiResource line, and add the `ClientProfileController` import at the top with the other `use` statements:

```php
use App\Http\Controllers\Api\Nutrition\AiConversationController;
use App\Http\Controllers\Api\Nutrition\AppointmentController;
use App\Http\Controllers\Api\Nutrition\ClientController;
use App\Http\Controllers\Api\Nutrition\ClientProfileController;
use App\Http\Controllers\Api\Nutrition\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('nutrition')->middleware(['auth:sanctum', 'active.clinic'])->group(function () {
    Route::apiResource('clients', ClientController::class)->names('nutrition.clients');
    Route::get('clients/{client}/profile', [ClientProfileController::class, 'show'])->name('nutrition.clients.profile.show');
    Route::put('clients/{client}/profile', [ClientProfileController::class, 'update'])->name('nutrition.clients.profile.update');
```

(Leave the rest of the file — `appointments`, `dashboard/stats`, the `ai-conversation`/`kvkk.consent` group — exactly as it is.)

- [ ] **Step 7: Run the test to verify it passes**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan test tests/Feature/Nutrition/ClientProfileTest.php`
Expected: `PASS` — 4 tests, 4 assertions groups all green.

- [ ] **Step 8: Run the full Nutrition test suite to check for regressions**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan test tests/Feature/Nutrition/`
Expected: all tests pass (the 4 pre-existing Nutrition test files plus the new `ClientProfileTest.php`).

- [ ] **Step 9: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add tests/Feature/Nutrition/ClientProfileTest.php app/Http/Requests/Nutrition/UpdateNutritionClientProfileRequest.php app/Http/Resources/NutritionClientProfileResource.php app/Http/Controllers/Api/Nutrition/ClientProfileController.php routes/api/nutrition.php
git commit -m "$(cat <<'EOF'
feat: add nutrition client profile show/update endpoint

GET/PUT /api/nutrition/clients/{client}/profile -- same
AuthorizesOwnDoctorRecords ownership gate as the other Nutrition
client endpoints, firstOrCreate on first access so every client
always has a profile row once touched.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Frontend API client methods

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\lib\api.js`

- [ ] **Step 1: Add `clientProfile` methods to the `nutrition` namespace**

Find (per the existing `nutrition` namespace):

```js
  nutrition: {
    confirmPackage: (clientId, payload, token) =>
      request(`/clients/${clientId}/nutrition-care-plan/confirm`, {
        method: "POST",
        body: payload,
        token,
      }),
    clients: {
      listPaginated: (query, token) =>
        request("/nutrition/clients", { token, query }),
      create: (payload, token) =>
        request("/nutrition/clients", { method: "POST", body: payload, token }),
    },
```

Replace with (adds a new `clientProfile` sub-object, leaves everything else in the `nutrition` namespace untouched):

```js
  nutrition: {
    confirmPackage: (clientId, payload, token) =>
      request(`/clients/${clientId}/nutrition-care-plan/confirm`, {
        method: "POST",
        body: payload,
        token,
      }),
    clients: {
      listPaginated: (query, token) =>
        request("/nutrition/clients", { token, query }),
      create: (payload, token) =>
        request("/nutrition/clients", { method: "POST", body: payload, token }),
    },
    clientProfile: {
      show: (clientId, token) =>
        request(`/nutrition/clients/${clientId}/profile`, { token }),
      update: (clientId, payload, token) =>
        request(`/nutrition/clients/${clientId}/profile`, { method: "PUT", body: payload, token }),
    },
```

- [ ] **Step 2: Verify the file still parses**

Run: `cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend" && node --check src/lib/api.js`
Expected: no output (exit code 0 means valid syntax).

- [ ] **Step 3: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/lib/api.js
git commit -m "$(cat <<'EOF'
feat: add nutrition client profile API client methods

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Editable nutrition profile section in `NutritionClientEditPage.jsx`

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionClientEditPage.jsx`

This follows the same "local state + direct `api.nutrition.*` call + own save button" pattern already used by `NutritionCarePlanModal.jsx` (see that file for precedent) — it does not touch the shared `ClientFormFields.jsx` component every specialty's edit page uses for the base client fields (name/phone/email/etc.), since this profile is nutrition-only data on a separate table with its own endpoint.

- [ ] **Step 1: Add the new imports and local state**

Find the top of the file:

```jsx
import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import ClientFormFields from "../../../components/ClientFormFields";
import SpecialtyShell from "../../../components/SpecialtyShell";
import { useAppState } from "../../../context/useAppState";
import { useLanguage } from "../../../context/useLanguage";
import theme from "../theme";

export default function NutritionClientEditPage() {
  const navigate = useNavigate();
  const { clientId } = useParams();
  const {
    loadClientBundle,
    clientDraft,
    startNewClientDraft,
    selectClientForEdit,
    clients,
    loadClients,
  } = useAppState();
  const { t } = useLanguage();
```

Replace with:

```jsx
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import ClientFormFields from "../../../components/ClientFormFields";
import FancySelect from "../../../components/FancySelect";
import FormField from "../../../components/FormField";
import SpecialtyShell from "../../../components/SpecialtyShell";
import { useAppState } from "../../../context/useAppState";
import { useLanguage } from "../../../context/useLanguage";
import { api } from "../../../lib/api";
import theme from "../theme";

const EMPTY_PROFILE = {
  heightCm: "",
  dietaryType: "",
  allergies: "",
  chronicConditions: "",
  medicationsAffectingDiet: "",
  smokingStatus: "",
  alcoholStatus: "",
  activityLevel: "",
  goal: "",
  targetWeightKg: "",
  notes: "",
};

function profileFromApi(data) {
  return {
    heightCm: data?.height_cm ?? "",
    dietaryType: data?.dietary_type ?? "",
    allergies: (data?.allergies ?? []).join(", "),
    chronicConditions: (data?.chronic_conditions ?? []).join(", "),
    medicationsAffectingDiet: data?.medications_affecting_diet ?? "",
    smokingStatus: data?.smoking_status ?? "",
    alcoholStatus: data?.alcohol_status ?? "",
    activityLevel: data?.activity_level ?? "",
    goal: data?.goal ?? "",
    targetWeightKg: data?.target_weight_kg ?? "",
    notes: data?.notes ?? "",
  };
}

function splitTags(value) {
  return String(value || "")
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

export default function NutritionClientEditPage() {
  const navigate = useNavigate();
  const { clientId } = useParams();
  const {
    authToken,
    loadClientBundle,
    clientDraft,
    startNewClientDraft,
    selectClientForEdit,
    clients,
    loadClients,
  } = useAppState();
  const { t } = useLanguage();

  const [profileDraft, setProfileDraft] = useState(EMPTY_PROFILE);
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [profileSavedMessage, setProfileSavedMessage] = useState("");
```

- [ ] **Step 2: Fetch the existing profile once the client is known**

Find the existing data-loading `useEffect` in full (it's unchanged by this plan — shown in full so you can locate its exact closing lines to insert after):

```jsx
  useEffect(() => {
    if (isCreateMode) {
      startNewClientDraft();
      return;
    }

    if (existingClient) {
      selectClientForEdit(existingClient);

      if (loadedBundleForClientId === clientId) {
        return;
      }

      void loadClientBundle(existingClient.id, { force: true }).finally(() =>
        setLoadedBundleForClientId(clientId),
      );
      return;
    }

    if (attemptedClientId === clientId) {
      return;
    }

    void loadClients({ force: true }).finally(() => setAttemptedClientId(clientId));
  }, [
    attemptedClientId,
    clientId,
    existingClient,
    isCreateMode,
    loadClientBundle,
    loadClients,
    loadedBundleForClientId,
    selectClientForEdit,
    startNewClientDraft,
  ]);
```

Leave that `useEffect` byte-for-byte untouched. Insert a new, separate `useEffect` immediately after its closing `]);` line shown above (still before the component's `return (`):

```jsx
  useEffect(() => {
    if (isCreateMode || !existingClient || !authToken) {
      setProfileDraft(EMPTY_PROFILE);
      return;
    }

    let isCancelled = false;

    void api.nutrition.clientProfile.show(existingClient.id, authToken).then((response) => {
      if (!isCancelled) {
        setProfileDraft(profileFromApi(response?.data));
      }
    });

    return () => {
      isCancelled = true;
    };
  }, [authToken, existingClient, isCreateMode]);
```

- [ ] **Step 3: Add the enum option lists and the save handler**

Add this right after the new `useEffect` from Step 2, still before the component's `return (`:

```jsx
  const dietaryTypeOptions = useMemo(
    () => [
      { value: "omnivore", label: t("dietaryTypeOmnivore") },
      { value: "vegetarian", label: t("dietaryTypeVegetarian") },
      { value: "vegan", label: t("dietaryTypeVegan") },
      { value: "halal", label: t("dietaryTypeHalal") },
      { value: "kosher", label: t("dietaryTypeKosher") },
      { value: "other", label: t("dietaryTypeOther") },
    ],
    [t],
  );

  const substanceUseOptions = useMemo(
    () => [
      { value: "none", label: t("substanceStatusNone") },
      { value: "occasional", label: t("substanceStatusOccasional") },
      { value: "regular", label: t("substanceStatusRegular") },
    ],
    [t],
  );

  const activityLevelOptions = useMemo(
    () => [
      { value: "sedentary", label: t("activityLevelSedentary") },
      { value: "light", label: t("activityLevelLight") },
      { value: "moderate", label: t("activityLevelModerate") },
      { value: "active", label: t("activityLevelActive") },
      { value: "very_active", label: t("activityLevelVeryActive") },
    ],
    [t],
  );

  const goalOptions = useMemo(
    () => [
      { value: "weight_loss", label: t("goalWeightLoss") },
      { value: "weight_gain", label: t("goalWeightGain") },
      { value: "maintenance", label: t("goalMaintenance") },
      { value: "muscle_gain", label: t("goalMuscleGain") },
      { value: "medical_diet", label: t("goalMedicalDiet") },
      { value: "other", label: t("dietaryTypeOther") },
    ],
    [t],
  );

  const handleSaveProfile = async () => {
    if (!existingClient || !authToken) return;

    setIsSavingProfile(true);
    setProfileSavedMessage("");

    try {
      const response = await api.nutrition.clientProfile.update(
        existingClient.id,
        {
          height_cm: profileDraft.heightCm === "" ? null : Number(profileDraft.heightCm),
          dietary_type: profileDraft.dietaryType || null,
          allergies: splitTags(profileDraft.allergies),
          chronic_conditions: splitTags(profileDraft.chronicConditions),
          medications_affecting_diet: profileDraft.medicationsAffectingDiet || null,
          smoking_status: profileDraft.smokingStatus || null,
          alcohol_status: profileDraft.alcoholStatus || null,
          activity_level: profileDraft.activityLevel || null,
          goal: profileDraft.goal || null,
          target_weight_kg: profileDraft.targetWeightKg === "" ? null : Number(profileDraft.targetWeightKg),
          notes: profileDraft.notes || null,
        },
        authToken,
      );
      setProfileDraft(profileFromApi(response?.data));
      setProfileSavedMessage(t("nutritionProfileSaved"));
    } finally {
      setIsSavingProfile(false);
    }
  };
```

- [ ] **Step 4: Render the profile section**

Find the end of the component's JSX:

```jsx
              <div className="section-heading">
                <span>{t("editFile")}</span>
                <h2>{clientDraft.name || t("addClient")}</h2>
              </div>
              <ClientFormFields onSaved={(savedClient) => navigate(`/nutrition/client-details/${savedClient.id}`)} />
            </>
          )}
        </section>
      </section>
    </SpecialtyShell>
  );
}
```

Replace with (adds a second `detail-card` section, only rendered when editing an existing client — a brand-new client has to be saved once via `ClientFormFields` before it has an id to attach a profile to):

```jsx
              <div className="section-heading">
                <span>{t("editFile")}</span>
                <h2>{clientDraft.name || t("addClient")}</h2>
              </div>
              <ClientFormFields onSaved={(savedClient) => navigate(`/nutrition/client-details/${savedClient.id}`)} />
            </>
          )}
        </section>

        {!isCreateMode && existingClient ? (
          <section className="detail-card">
            <div className="section-heading">
              <span>{t("nutritionProfileSectionTitle")}</span>
              <h2>{t("nutritionProfileSectionTitle")}</h2>
            </div>
            <div className="form-grid">
              <FormField
                label={t("heightCm")}
                type="number"
                value={String(profileDraft.heightCm ?? "")}
                onChange={(event) => setProfileDraft({ ...profileDraft, heightCm: event.target.value })}
                min="0"
                max="999"
              />
              <div className="form-field">
                <span className="field-label">{t("dietaryType")}</span>
                <FancySelect
                  bare
                  label={t("dietaryType")}
                  value={profileDraft.dietaryType}
                  onChange={(event) => setProfileDraft({ ...profileDraft, dietaryType: event.target.value })}
                  options={dietaryTypeOptions}
                  placeholder={t("dietaryType")}
                />
              </div>
              <FormField
                label={t("allergies")}
                value={profileDraft.allergies}
                onChange={(event) => setProfileDraft({ ...profileDraft, allergies: event.target.value })}
                placeholder={t("allergiesPlaceholder")}
              />
              <FormField
                label={t("chronicConditions")}
                value={profileDraft.chronicConditions}
                onChange={(event) => setProfileDraft({ ...profileDraft, chronicConditions: event.target.value })}
                placeholder={t("chronicConditionsPlaceholder")}
              />
              <FormField
                label={t("medicationsAffectingDiet")}
                value={profileDraft.medicationsAffectingDiet}
                onChange={(event) => setProfileDraft({ ...profileDraft, medicationsAffectingDiet: event.target.value })}
                textarea
              />
              <div className="form-field">
                <span className="field-label">{t("smokingStatus")}</span>
                <FancySelect
                  bare
                  label={t("smokingStatus")}
                  value={profileDraft.smokingStatus}
                  onChange={(event) => setProfileDraft({ ...profileDraft, smokingStatus: event.target.value })}
                  options={substanceUseOptions}
                  placeholder={t("smokingStatus")}
                />
              </div>
              <div className="form-field">
                <span className="field-label">{t("alcoholStatus")}</span>
                <FancySelect
                  bare
                  label={t("alcoholStatus")}
                  value={profileDraft.alcoholStatus}
                  onChange={(event) => setProfileDraft({ ...profileDraft, alcoholStatus: event.target.value })}
                  options={substanceUseOptions}
                  placeholder={t("alcoholStatus")}
                />
              </div>
              <div className="form-field">
                <span className="field-label">{t("activityLevel")}</span>
                <FancySelect
                  bare
                  label={t("activityLevel")}
                  value={profileDraft.activityLevel}
                  onChange={(event) => setProfileDraft({ ...profileDraft, activityLevel: event.target.value })}
                  options={activityLevelOptions}
                  placeholder={t("activityLevel")}
                />
              </div>
              <div className="form-field">
                <span className="field-label">{t("nutritionGoal")}</span>
                <FancySelect
                  bare
                  label={t("nutritionGoal")}
                  value={profileDraft.goal}
                  onChange={(event) => setProfileDraft({ ...profileDraft, goal: event.target.value })}
                  options={goalOptions}
                  placeholder={t("nutritionGoal")}
                />
              </div>
              <FormField
                label={t("targetWeightKg")}
                type="number"
                value={String(profileDraft.targetWeightKg ?? "")}
                onChange={(event) => setProfileDraft({ ...profileDraft, targetWeightKg: event.target.value })}
                min="0"
                max="999"
              />
              <FormField
                label={t("nutritionProfileNotes")}
                value={profileDraft.notes}
                onChange={(event) => setProfileDraft({ ...profileDraft, notes: event.target.value })}
                textarea
              />
            </div>
            <div className="panel-actions">
              <button type="button" className="primary-button" disabled={isSavingProfile} onClick={() => void handleSaveProfile()}>
                {t("saveNutritionProfile")}
              </button>
              {profileSavedMessage ? <span className="form-success-note">{profileSavedMessage}</span> : null}
            </div>
          </section>
        ) : null}
      </section>
    </SpecialtyShell>
  );
}
```

- [ ] **Step 5: Verify the file still parses**

Run: `cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend" && node --check src/specialties/nutrition/pages/NutritionClientEditPage.jsx`

Note: `node --check` validates plain JS syntax, not JSX — if it errors on the JSX itself, that's expected and not a real problem; the authoritative check is `npm run build` in Task 9. Use this step only to catch obvious typos (unbalanced braces/parens) early.

- [ ] **Step 6: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/specialties/nutrition/pages/NutritionClientEditPage.jsx
git commit -m "$(cat <<'EOF'
feat: add editable nutrition profile section to client edit page

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Read-only nutrition profile display in `NutritionClientDetailsPage.jsx`

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionClientDetailsPage.jsx`

- [ ] **Step 1: Add the import and local state**

Find the top of the file (post Sub-project-1's edits, so no `XrayImagePicker` import is present anymore):

```jsx
import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import ActionIcon from "../../../components/ActionIcon";
import ClientPaymentsPanel from "../../../components/ClientPaymentsPanel";
import ClientConsentsPanel from "../../../components/ClientConsentsPanel";
import ClientCarePlansPanel from "../../../components/ClientCarePlansPanel";
import ClientLabResultsPanel from "../../../components/ClientLabResultsPanel";
import ClientPrescriptionsPanel from "../../../components/ClientPrescriptionsPanel";
import ClientTimelinePanel from "../../../components/ClientTimelinePanel";
import SpecialtyShell from "../../../components/SpecialtyShell";
import { useAppState } from "../../../context/useAppState";
import { useLanguage } from "../../../context/useLanguage";
import PlanExportModal from "../../../components/PlanExportModal";
import NutritionCarePlanModal from "../../../components/NutritionCarePlanModal";
import AiTreatmentPlanModal from "../../../components/AiTreatmentPlanModal";
import theme from "../theme";
```

Replace with:

```jsx
import { useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import ActionIcon from "../../../components/ActionIcon";
import ClientPaymentsPanel from "../../../components/ClientPaymentsPanel";
import ClientConsentsPanel from "../../../components/ClientConsentsPanel";
import ClientCarePlansPanel from "../../../components/ClientCarePlansPanel";
import ClientLabResultsPanel from "../../../components/ClientLabResultsPanel";
import ClientPrescriptionsPanel from "../../../components/ClientPrescriptionsPanel";
import ClientTimelinePanel from "../../../components/ClientTimelinePanel";
import SpecialtyShell from "../../../components/SpecialtyShell";
import { useAppState } from "../../../context/useAppState";
import { useLanguage } from "../../../context/useLanguage";
import { api } from "../../../lib/api";
import PlanExportModal from "../../../components/PlanExportModal";
import NutritionCarePlanModal from "../../../components/NutritionCarePlanModal";
import AiTreatmentPlanModal from "../../../components/AiTreatmentPlanModal";
import theme from "../theme";

const DIETARY_TYPE_LABEL_KEYS = {
  omnivore: "dietaryTypeOmnivore",
  vegetarian: "dietaryTypeVegetarian",
  vegan: "dietaryTypeVegan",
  halal: "dietaryTypeHalal",
  kosher: "dietaryTypeKosher",
  other: "dietaryTypeOther",
};

const SUBSTANCE_STATUS_LABEL_KEYS = {
  none: "substanceStatusNone",
  occasional: "substanceStatusOccasional",
  regular: "substanceStatusRegular",
};

const ACTIVITY_LEVEL_LABEL_KEYS = {
  sedentary: "activityLevelSedentary",
  light: "activityLevelLight",
  moderate: "activityLevelModerate",
  active: "activityLevelActive",
  very_active: "activityLevelVeryActive",
};

const GOAL_LABEL_KEYS = {
  weight_loss: "goalWeightLoss",
  weight_gain: "goalWeightGain",
  maintenance: "goalMaintenance",
  muscle_gain: "goalMuscleGain",
  medical_diet: "goalMedicalDiet",
  other: "dietaryTypeOther",
};
```

- [ ] **Step 2: Fetch the profile alongside the client bundle**

Find the component's existing data-loading `useEffect` and state declarations:

```jsx
  const [isNutritionCarePlanModalOpen, setIsNutritionCarePlanModalOpen] = useState(false);
  const [activePanel, setActivePanel] = useState("clientData");
  const [isPlanExportModalOpen, setIsPlanExportModalOpen] = useState(false);
  const [pendingPaymentDate, setPendingPaymentDate] = useState(null);

  useEffect(() => {
    void loadDoctors();
    void loadCompanyTreatmentProducts();
    void refreshAppointments({ force: true });
    if (clientId) {
      setSelectedClient(clientId);
      void loadClientBundle(clientId, { force: true });
    }
  }, [clientId, loadClientBundle, loadCompanyTreatmentProducts, loadDoctors, refreshAppointments, setSelectedClient]);
```

Replace with:

```jsx
  const [isNutritionCarePlanModalOpen, setIsNutritionCarePlanModalOpen] = useState(false);
  const [activePanel, setActivePanel] = useState("clientData");
  const [isPlanExportModalOpen, setIsPlanExportModalOpen] = useState(false);
  const [pendingPaymentDate, setPendingPaymentDate] = useState(null);
  const [nutritionProfile, setNutritionProfile] = useState(null);

  useEffect(() => {
    void loadDoctors();
    void loadCompanyTreatmentProducts();
    void refreshAppointments({ force: true });
    if (clientId) {
      setSelectedClient(clientId);
      void loadClientBundle(clientId, { force: true });
    }
  }, [clientId, loadClientBundle, loadCompanyTreatmentProducts, loadDoctors, refreshAppointments, setSelectedClient]);

  useEffect(() => {
    if (!clientId || !authToken) {
      setNutritionProfile(null);
      return;
    }

    let isCancelled = false;

    void api.nutrition.clientProfile.show(clientId, authToken).then((response) => {
      if (!isCancelled) {
        setNutritionProfile(response?.data ?? null);
      }
    });

    return () => {
      isCancelled = true;
    };
  }, [authToken, clientId]);
```

`authToken` is NOT currently in this component's `useAppState()` destructure — it must be added. Find:

```jsx
  const {
    selectedClient,
    authUser,
    isSystemManager,
    canAccessNutrition,
    loadDoctors,
    loadCompanyTreatmentProducts,
    loadClientBundle,
    refreshAppointments,
    selectedClientVisits,
    selectedClientPayments,
    selectedClientFinancials,
    selectedClientUpcomingAppointment,
    markAppointmentAsNoShow,
    deleteFutureAppointment,
    getAppointmentActionState,
    setSelectedClient,
    deleteClient,
  } = useAppState();
```

Replace with (adds `authToken` as the first entry):

```jsx
  const {
    authToken,
    selectedClient,
    authUser,
    isSystemManager,
    canAccessNutrition,
    loadDoctors,
    loadCompanyTreatmentProducts,
    loadClientBundle,
    refreshAppointments,
    selectedClientVisits,
    selectedClientPayments,
    selectedClientFinancials,
    selectedClientUpcomingAppointment,
    markAppointmentAsNoShow,
    deleteFutureAppointment,
    getAppointmentActionState,
    setSelectedClient,
    deleteClient,
  } = useAppState();
```

- [ ] **Step 3: Render the profile fields in the "clientData" panel**

Find the existing `clientData` panel's detail list:

```jsx
          <ul className="detail-list horizontal-detail-list">
            <li><span>{t("clientName")}</span><strong>{selectedClient.name}</strong></li>
            <li><span>{t("phone")}</span><strong>{selectedClient.phone}</strong></li>
            <li><span>{t("email")}</span><strong>{selectedClient.email}</strong></li>
            <li><span>{t("cityColumn")}</span><strong>{selectedClient.city}</strong></li>
            <li><span>{t("age")}</span><strong>{selectedClient.age}</strong></li>
            <li><span>{t("address")}</span><strong>{selectedClient.address}</strong></li>
            <li><span>{t("medicalNotes")}</span><strong>{selectedClient.medicalNotes}</strong></li>
          </ul>
```

Replace with (appends the nutrition-specific fields after the generic ones, each falling back to an em-dash when unset — `nutritionProfile` is `null` until the fetch resolves, so every access below is optional-chained):

```jsx
          <ul className="detail-list horizontal-detail-list">
            <li><span>{t("clientName")}</span><strong>{selectedClient.name}</strong></li>
            <li><span>{t("phone")}</span><strong>{selectedClient.phone}</strong></li>
            <li><span>{t("email")}</span><strong>{selectedClient.email}</strong></li>
            <li><span>{t("cityColumn")}</span><strong>{selectedClient.city}</strong></li>
            <li><span>{t("age")}</span><strong>{selectedClient.age}</strong></li>
            <li><span>{t("address")}</span><strong>{selectedClient.address}</strong></li>
            <li><span>{t("medicalNotes")}</span><strong>{selectedClient.medicalNotes}</strong></li>
            <li><span>{t("heightCm")}</span><strong>{nutritionProfile?.height_cm ?? "\u2014"}</strong></li>
            <li><span>{t("dietaryType")}</span><strong>{nutritionProfile?.dietary_type ? t(DIETARY_TYPE_LABEL_KEYS[nutritionProfile.dietary_type]) : "\u2014"}</strong></li>
            <li><span>{t("allergies")}</span><strong>{nutritionProfile?.allergies?.length ? nutritionProfile.allergies.join(", ") : "\u2014"}</strong></li>
            <li><span>{t("chronicConditions")}</span><strong>{nutritionProfile?.chronic_conditions?.length ? nutritionProfile.chronic_conditions.join(", ") : "\u2014"}</strong></li>
            <li><span>{t("medicationsAffectingDiet")}</span><strong>{nutritionProfile?.medications_affecting_diet || "\u2014"}</strong></li>
            <li><span>{t("smokingStatus")}</span><strong>{nutritionProfile?.smoking_status ? t(SUBSTANCE_STATUS_LABEL_KEYS[nutritionProfile.smoking_status]) : "\u2014"}</strong></li>
            <li><span>{t("alcoholStatus")}</span><strong>{nutritionProfile?.alcohol_status ? t(SUBSTANCE_STATUS_LABEL_KEYS[nutritionProfile.alcohol_status]) : "\u2014"}</strong></li>
            <li><span>{t("activityLevel")}</span><strong>{nutritionProfile?.activity_level ? t(ACTIVITY_LEVEL_LABEL_KEYS[nutritionProfile.activity_level]) : "\u2014"}</strong></li>
            <li><span>{t("nutritionGoal")}</span><strong>{nutritionProfile?.goal ? t(GOAL_LABEL_KEYS[nutritionProfile.goal]) : "\u2014"}</strong></li>
            <li><span>{t("targetWeightKg")}</span><strong>{nutritionProfile?.target_weight_kg ?? "\u2014"}</strong></li>
            <li><span>{t("nutritionProfileNotes")}</span><strong>{nutritionProfile?.notes || "\u2014"}</strong></li>
          </ul>
```

- [ ] **Step 4: Verify no leftover reference issues**

Run: `grep -n "authToken" "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionClientDetailsPage.jsx"`
Expected: at least 3 matches (the destructure, the new `useEffect`'s dependency array, and its body) — confirms `authToken` is properly wired.

- [ ] **Step 5: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/specialties/nutrition/pages/NutritionClientDetailsPage.jsx
git commit -m "$(cat <<'EOF'
feat: display nutrition profile fields on client details page

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Translations (ar/en/tr)

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\translations\en.json`
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\translations\ar.json`
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\translations\tr.json`

All three files are flat JSON with the exact formatting style `    "key":  "Value",` (4-space indent, two spaces after the colon) — match it exactly. Insert the new block right after the existing `"medicalNotes"` key in each file (found at line 113 in `en.json` in this codebase's current state; find the same key in `ar.json`/`tr.json`, the line number may differ slightly).

- [ ] **Step 1: Add the English keys**

In `en.json`, find:

```json
    "medicalNotes":  "Medical Notes",
```

Insert immediately after it:

```json
    "medicalNotes":  "Medical Notes",
    "nutritionProfileSectionTitle":  "Nutrition Profile",
    "heightCm":  "Height (cm)",
    "dietaryType":  "Dietary Type",
    "dietaryTypeOmnivore":  "Omnivore",
    "dietaryTypeVegetarian":  "Vegetarian",
    "dietaryTypeVegan":  "Vegan",
    "dietaryTypeHalal":  "Halal",
    "dietaryTypeKosher":  "Kosher",
    "dietaryTypeOther":  "Other",
    "allergies":  "Allergies",
    "allergiesPlaceholder":  "Separate with commas, e.g. peanuts, shellfish",
    "chronicConditions":  "Chronic Conditions",
    "chronicConditionsPlaceholder":  "Separate with commas, e.g. diabetes, hypertension",
    "medicationsAffectingDiet":  "Medications Affecting Diet",
    "smokingStatus":  "Smoking",
    "alcoholStatus":  "Alcohol",
    "substanceStatusNone":  "None",
    "substanceStatusOccasional":  "Occasional",
    "substanceStatusRegular":  "Regular",
    "activityLevel":  "Activity Level",
    "activityLevelSedentary":  "Sedentary",
    "activityLevelLight":  "Light",
    "activityLevelModerate":  "Moderate",
    "activityLevelActive":  "Active",
    "activityLevelVeryActive":  "Very Active",
    "nutritionGoal":  "Goal",
    "goalWeightLoss":  "Weight Loss",
    "goalWeightGain":  "Weight Gain",
    "goalMaintenance":  "Maintenance",
    "goalMuscleGain":  "Muscle Gain",
    "goalMedicalDiet":  "Medical Diet",
    "targetWeightKg":  "Target Weight (kg)",
    "nutritionProfileNotes":  "Nutrition Notes",
    "saveNutritionProfile":  "Save Nutrition Profile",
    "nutritionProfileSaved":  "Nutrition profile saved",
```

- [ ] **Step 2: Add the Arabic keys**

In `ar.json`, find the equivalent `"medicalNotes"` line and insert immediately after it:

```json
    "nutritionProfileSectionTitle":  "الملف التغذوي",
    "heightCm":  "الطول (سم)",
    "dietaryType":  "نوع النظام الغذائي",
    "dietaryTypeOmnivore":  "متنوع",
    "dietaryTypeVegetarian":  "نباتي (يتناول الألبان والبيض)",
    "dietaryTypeVegan":  "نباتي صرف",
    "dietaryTypeHalal":  "حلال",
    "dietaryTypeKosher":  "كوشر",
    "dietaryTypeOther":  "أخرى",
    "allergies":  "الحساسية",
    "allergiesPlaceholder":  "افصل بينها بفاصلة، مثال: الفول السوداني، المحار",
    "chronicConditions":  "الأمراض المزمنة",
    "chronicConditionsPlaceholder":  "افصل بينها بفاصلة، مثال: السكري، ضغط الدم",
    "medicationsAffectingDiet":  "أدوية تؤثر على التغذية",
    "smokingStatus":  "التدخين",
    "alcoholStatus":  "الكحول",
    "substanceStatusNone":  "لا يوجد",
    "substanceStatusOccasional":  "أحياناً",
    "substanceStatusRegular":  "بانتظام",
    "activityLevel":  "مستوى النشاط البدني",
    "activityLevelSedentary":  "خامل",
    "activityLevelLight":  "نشاط خفيف",
    "activityLevelModerate":  "نشاط متوسط",
    "activityLevelActive":  "نشط",
    "activityLevelVeryActive":  "نشط جداً",
    "nutritionGoal":  "الهدف",
    "goalWeightLoss":  "خسارة الوزن",
    "goalWeightGain":  "زيادة الوزن",
    "goalMaintenance":  "الحفاظ على الوزن",
    "goalMuscleGain":  "زيادة الكتلة العضلية",
    "goalMedicalDiet":  "حمية طبية",
    "targetWeightKg":  "الوزن المستهدف (كغ)",
    "nutritionProfileNotes":  "ملاحظات تغذوية",
    "saveNutritionProfile":  "حفظ الملف التغذوي",
    "nutritionProfileSaved":  "تم حفظ الملف التغذوي",
```

- [ ] **Step 3: Add the Turkish keys**

In `tr.json`, find the equivalent `"medicalNotes"` line and insert immediately after it:

```json
    "nutritionProfileSectionTitle":  "Beslenme Profili",
    "heightCm":  "Boy (cm)",
    "dietaryType":  "Diyet Tipi",
    "dietaryTypeOmnivore":  "Karma Beslenen",
    "dietaryTypeVegetarian":  "Vejetaryen",
    "dietaryTypeVegan":  "Vegan",
    "dietaryTypeHalal":  "Helal",
    "dietaryTypeKosher":  "Ko\u015fer",
    "dietaryTypeOther":  "Di\u011fer",
    "allergies":  "Alerjiler",
    "allergiesPlaceholder":  "Virg\u00fclle ay\u0131r\u0131n, \u00f6rn. f\u0131st\u0131k, kabuklu deniz \u00fcr\u00fcnleri",
    "chronicConditions":  "Kronik Hastal\u0131klar",
    "chronicConditionsPlaceholder":  "Virg\u00fclle ay\u0131r\u0131n, \u00f6rn. diyabet, hipertansiyon",
    "medicationsAffectingDiet":  "Beslenmeyi Etkileyen \u0130la\u00e7lar",
    "smokingStatus":  "Sigara Kullan\u0131m\u0131",
    "alcoholStatus":  "Alkol Kullan\u0131m\u0131",
    "substanceStatusNone":  "Yok",
    "substanceStatusOccasional":  "Ara S\u0131ra",
    "substanceStatusRegular":  "D\u00fczenli",
    "activityLevel":  "Aktivite Seviyesi",
    "activityLevelSedentary":  "Hareketsiz",
    "activityLevelLight":  "Hafif",
    "activityLevelModerate":  "Orta",
    "activityLevelActive":  "Aktif",
    "activityLevelVeryActive":  "\u00c7ok Aktif",
    "nutritionGoal":  "Hedef",
    "goalWeightLoss":  "Kilo Verme",
    "goalWeightGain":  "Kilo Alma",
    "goalMaintenance":  "Kilo Koruma",
    "goalMuscleGain":  "Kas Kazan\u0131m\u0131",
    "goalMedicalDiet":  "T\u0131bbi Diyet",
    "targetWeightKg":  "Hedef Kilo (kg)",
    "nutritionProfileNotes":  "Beslenme Notlar\u0131",
    "saveNutritionProfile":  "Beslenme Profilini Kaydet",
    "nutritionProfileSaved":  "Beslenme profili kaydedildi",
```

Note: the Turkish block above is written with literal `\u00XX`/`\u015X` escapes for every Turkish-specific character (ş, ı, ğ, ü, ö, ç and their uppercase forms) instead of raw UTF-8, since JSON string escapes are the safest way to guarantee the exact diacritics survive this document being copied verbatim — `\u00e7`=ç, `\u00fc`=ü, `\u00f6`=ö, `\u011f`=ğ, `\u015f`=ş, `\u0131`=ı (dotless i), `\u0130`=İ (dotted capital I), `\u015e`=Ş, `\u00c7`=Ç. When implementing this step, either paste the escapes exactly as shown (valid JSON, renders correctly at runtime) or substitute the literal UTF-8 characters they represent — do not substitute ASCII look-alikes (e.g. never write plain `s` for `ş` or plain `i` for `ı`).

- [ ] **Step 4: Verify all three files are still valid JSON**

Run: `cd "C:\Users\MK\Desktop\Dental_FrontEnd" && node -e "JSON.parse(require('fs').readFileSync('app/translations/en.json')); JSON.parse(require('fs').readFileSync('app/translations/ar.json')); JSON.parse(require('fs').readFileSync('app/translations/tr.json')); console.log('all valid');"`
Expected: `all valid`

- [ ] **Step 5: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/translations/en.json app/translations/ar.json app/translations/tr.json
git commit -m "$(cat <<'EOF'
feat: add ar/en/tr translations for the nutrition profile fields

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Lint, build, deploy, and verify

**Files:**
- No new files — builds and deploys the changes from Tasks 5–8 (Tasks 1–4 are backend-only and already verified via `php artisan test` in Task 4).

- [ ] **Step 1: Lint the frontend**

Run:
```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend"
NODE_OPTIONS=--max-old-space-size=4096 npm run lint
```
Expected: same pre-existing repo-wide debt as before (~1224 errors, unrelated to this change — already confirmed as baseline in the Sub-project 1 plan). Confirm no NEW errors mentioning `NutritionClientEditPage.jsx`, `NutritionClientDetailsPage.jsx`, `api.js`, `FancySelect`, or `FormField` that would indicate a broken reference.

- [ ] **Step 2: Build the frontend**

Run:
```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend"
npm run build
```
Expected: succeeds, writes to `dist/`. This is the authoritative JSX/syntax check for Task 6/7's edits.

- [ ] **Step 3: Deploy the built assets**

```bash
rm -rf "C:\Users\MK\Desktop\Dental_Backend\public\app"/*
cp -r "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\dist"/* "C:\Users\MK\Desktop\Dental_Backend\public\app"
```
(If the trailing-backslash-before-closing-quote shell-quoting issue shows up again, drop the trailing backslash — known POSIX-sh quirk in this environment, not a real problem.)

- [ ] **Step 4: Commit the refreshed built assets**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add public/app
git commit -m "$(cat <<'EOF'
chore: refresh built frontend assets in public/app

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Re-run the backend Nutrition test suite once more as a final sanity check**

Run: `cd "C:\Users\MK\Desktop\Dental_Backend" && php artisan test tests/Feature/Nutrition/`
Expected: all tests pass.

- [ ] **Step 6: Report status**

No automated frontend test exists for the new UI — state plainly that lint/build passed and that a real browser check of the new profile fields (edit page save flow, details page display) still needs a human or a tool capable of driving a real browser, per this project's established convention that UI changes need eyes-on verification before being called done.
