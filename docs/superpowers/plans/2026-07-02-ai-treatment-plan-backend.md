# AI Treatment Plan Assistant — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two Laravel API endpoints that let a doctor turn a free-text case description into a reviewable, then confirmable, multi-session treatment plan — creating real `Appointment` rows with AI-generated Odontogram v2 data attached, and carrying that data into the `Visit` automatically at check-in.

**Architecture:** A new `OpenAiClient` service wraps the two OpenAI HTTP calls (structured chat completion + Whisper transcription) behind Laravel's `Http` facade — no new composer dependency. A new `AiTreatmentPlanService` owns the domain logic: building the constrained JSON schema, resolving real appointment slots via the existing `DoctorAvailabilityService`/`AppointmentConflictService`, and persisting confirmed sessions as `Appointment` rows inside a transaction. Two new endpoints (`preview`, `confirm`) sit behind a new `AiTreatmentPlanController`, following the existing `Client{X}Controller` + `Service` + `FormRequest` pattern used throughout this codebase.

**Tech Stack:** Laravel 12, Sanctum, PHPUnit (existing `tests/Feature` and `tests/Unit` split), SQLite in-memory for tests, Laravel `Http` facade with `Http::fake()`.

**Spec:** `docs/superpowers/specs/2026-07-02-ai-treatment-plan-assistant-design.md` — read this first for the "why" behind the `planned_*` columns living on `Appointment` instead of a pre-created `Visit`.

---

## File Structure

**New files:**
- `database/migrations/2026_07_02_090000_add_planned_fields_to_appointments_table.php` — adds `planned_summary`, `planned_notes`, `planned_image_path` to `appointments`.
- `database/migrations/2026_07_02_090100_add_odontogram_image_path_to_visits_table.php` — adds `odontogram_image_path` to `visits`.
- `app/Services/OdontogramV2Vocabulary.php` — static arrays of the Odontogram v2 tooth-condition vocabulary the AI is allowed to use (mirrors `odontogramV2.js`'s `DEFAULT_PRICE_BY_KIND` on the frontend).
- `app/Services/OpenAiClient.php` — thin HTTP wrapper for OpenAI chat completions (structured JSON output) and Whisper transcription.
- `app/Services/AiTreatmentPlanService.php` — domain logic: JSON schema construction, slot resolution, odontogram-shape translation, and persistence.
- `app/Http/Requests/AiTreatmentPlan/PreviewAiTreatmentPlanRequest.php`
- `app/Http/Requests/AiTreatmentPlan/ConfirmAiTreatmentPlanRequest.php`
- `app/Http/Controllers/Api/AiTreatmentPlanController.php`
- `tests/Unit/Services/OdontogramV2VocabularyTest.php`
- `tests/Unit/Services/OpenAiClientTest.php`
- `tests/Unit/Services/AiTreatmentPlanServiceSchemaTest.php`
- `tests/Unit/Services/AiTreatmentPlanServiceSlotTest.php`
- `tests/Unit/Services/AiTreatmentPlanServiceOdontogramTest.php`
- `tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`
- `tests/Feature/AiTreatmentPlan/ConfirmAiTreatmentPlanTest.php`
- `tests/Feature/AiTreatmentPlan/CheckInAppliesPlannedDataTest.php`

**Modified files:**
- `app/Models/Appointment.php` — add `planned_summary`, `planned_notes`, `planned_image_path` to `$fillable`.
- `app/Models/Visit.php` — add `odontogram_image_path` to `$fillable`.
- `app/Http/Resources/AppointmentResource.php` — expose `planned_summary`, `planned_notes`, `planned_image_url`.
- `app/Http/Controllers/Api/ClientVisitController.php` — `checkIn()` defaults `summary`/`notes`/`odontogram_image_path` from the appointment's `planned_*` fields.
- `routes/api.php` — two new routes.
- `config/services.php` — `openai` config block.
- `.env.example` — `OPENAI_API_KEY`, `OPENAI_CHAT_MODEL`, `OPENAI_WHISPER_MODEL`.

---

### Task 1: Migrations and model fillable updates

**Files:**
- Create: `database/migrations/2026_07_02_090000_add_planned_fields_to_appointments_table.php`
- Create: `database/migrations/2026_07_02_090100_add_odontogram_image_path_to_visits_table.php`
- Modify: `app/Models/Appointment.php`
- Modify: `app/Models/Visit.php`

- [ ] **Step 1: Create the appointments migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->longText('planned_summary')->nullable()->after('notes');
            $table->longText('planned_notes')->nullable()->after('planned_summary');
            $table->string('planned_image_path')->nullable()->after('planned_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['planned_summary', 'planned_notes', 'planned_image_path']);
        });
    }
};
```

- [ ] **Step 2: Create the visits migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('odontogram_image_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('odontogram_image_path');
        });
    }
};
```

- [ ] **Step 3: Run the migrations against the local dev database**

Run: `php artisan migrate`
Expected: both new migrations run with no errors.

- [ ] **Step 4: Add the new columns to `Appointment::$fillable`**

In `app/Models/Appointment.php`, change:

```php
    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'type',
        'status',
        'date',
        'start_time',
        'duration_minutes',
        'end_time',
        'notes',
        'created_by',
        'updated_by',
    ];
```

to:

```php
    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'type',
        'status',
        'date',
        'start_time',
        'duration_minutes',
        'end_time',
        'notes',
        'planned_summary',
        'planned_notes',
        'planned_image_path',
        'created_by',
        'updated_by',
    ];
```

- [ ] **Step 5: Add the new column to `Visit::$fillable`**

In `app/Models/Visit.php`, change:

```php
    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'appointment_id',
        'visit_date',
        'start_time',
        'duration_minutes',
        'summary',
        'notes',
        'attendance_status',
        'created_by',
        'updated_by',
    ];
```

to:

```php
    protected $fillable = [
        'uuid',
        'client_id',
        'doctor_id',
        'appointment_id',
        'visit_date',
        'start_time',
        'duration_minutes',
        'summary',
        'notes',
        'odontogram_image_path',
        'attendance_status',
        'created_by',
        'updated_by',
    ];
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_02_090000_add_planned_fields_to_appointments_table.php database/migrations/2026_07_02_090100_add_odontogram_image_path_to_visits_table.php app/Models/Appointment.php app/Models/Visit.php
git commit -m "feat: add planned AI treatment plan columns to appointments and visits"
```

---

### Task 2: Odontogram v2 vocabulary constants

**Files:**
- Create: `app/Services/OdontogramV2Vocabulary.php`
- Test: `tests/Unit/Services/OdontogramV2VocabularyTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\OdontogramV2Vocabulary;
use PHPUnit\Framework\TestCase;

class OdontogramV2VocabularyTest extends TestCase
{
    public function test_it_exposes_the_priced_odontogram_v2_vocabulary(): void
    {
        $this->assertSame(
            ['implant', 'tooth-crownprep', 'tooth-under-gum', 'no-tooth-after-extraction'],
            OdontogramV2Vocabulary::toothSelection()
        );

        $this->assertContains('endo-filling', OdontogramV2Vocabulary::endo());
        $this->assertContains('amalgam', OdontogramV2Vocabulary::fillingMaterial());
        $this->assertContains('mesial', OdontogramV2Vocabulary::fillingSurfaces());
        $this->assertContains('caries-occlusal', OdontogramV2Vocabulary::caries());
        $this->assertContains('mobility', OdontogramV2Vocabulary::mods());
        $this->assertContains('pulpInflam', OdontogramV2Vocabulary::indicatorFlags());
        $this->assertContains('zircon', OdontogramV2Vocabulary::crownMaterial());
        $this->assertContains('bar-prosthesis', OdontogramV2Vocabulary::bridgeUnit());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Services/OdontogramV2VocabularyTest.php`
Expected: FAIL — `Class "App\Services\OdontogramV2Vocabulary" not found`.

- [ ] **Step 3: Create the vocabulary class**

This mirrors `DEFAULT_PRICE_BY_KIND` in `Dental_FrontEnd/app/frontend/src/utils/odontogramV2.js` exactly (that file is the source of truth — if it changes, update this list too) plus the `fillingSurfaces`/`caries` value lists and the boolean indicator flag names from the vendored `odontogram-v2/odontogram.ts` engine.

```php
<?php

namespace App\Services;

class OdontogramV2Vocabulary
{
    public static function toothSelection(): array
    {
        return ['implant', 'tooth-crownprep', 'tooth-under-gum', 'no-tooth-after-extraction'];
    }

    public static function crownMaterial(): array
    {
        return ['emax', 'zircon', 'metal', 'temporary', 'telescope', 'radix', 'broken'];
    }

    public static function bridgeUnit(): array
    {
        return ['zircon', 'metal', 'temporary', 'removable', 'bar', 'bar-prosthesis'];
    }

    public static function endo(): array
    {
        return [
            'endo-medical-filling',
            'endo-filling',
            'endo-filling-incomplete',
            'endo-glass-pin',
            'endo-metal-pin',
            'endo-resection',
            'parapulpal-pin',
        ];
    }

    public static function fillingMaterial(): array
    {
        return ['amalgam', 'composite', 'gic', 'temporary'];
    }

    public static function fillingSurfaces(): array
    {
        return ['buccal', 'lingual', 'mesial', 'distal', 'occlusal'];
    }

    public static function caries(): array
    {
        return ['caries-subcrown', 'caries-buccal', 'caries-lingual', 'caries-mesial', 'caries-distal', 'caries-occlusal'];
    }

    public static function mods(): array
    {
        return ['inflammation', 'parodontal', 'mobility'];
    }

    public static function indicatorFlags(): array
    {
        return [
            'crownNeeded',
            'crownReplace',
            'missingClosed',
            'extractionPlan',
            'extractionWound',
            'bridgePillar',
            'fissureSealing',
            'contactMesial',
            'contactDistal',
            'bruxismWear',
            'bruxismNeckWear',
            'pulpInflam',
            'endoResection',
            'parapulpalPin',
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Services/OdontogramV2VocabularyTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/OdontogramV2Vocabulary.php tests/Unit/Services/OdontogramV2VocabularyTest.php
git commit -m "feat: add Odontogram v2 vocabulary constants for AI treatment plan"
```

---

### Task 3: `OpenAiClient` — chat completion and transcription

**Files:**
- Create: `app/Services/OpenAiClient.php`
- Test: `tests/Unit/Services/OpenAiClientTest.php`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Add OpenAI config**

In `config/services.php`, add before the closing `];`:

```php
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'whisper_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],
```

- [ ] **Step 2: Add the env vars to `.env.example`**

Append to `.env.example`:

```
OPENAI_API_KEY=
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_WHISPER_MODEL=whisper-1
```

- [ ] **Step 3: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\OpenAiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
            'services.openai.whisper_model' => 'whisper-1',
        ]);
    }

    public function test_chat_completion_json_returns_decoded_content(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['diagnosis_summary' => 'ok', 'sessions' => []])]],
                ],
            ], 200),
        ]);

        $result = (new OpenAiClient())->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);

        $this->assertSame('ok', $result['diagnosis_summary']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['response_format']['type'] === 'json_schema';
        });
    }

    public function test_chat_completion_json_throws_when_request_fails(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => 'bad'], 500),
        ]);

        $this->expectException(ValidationException::class);

        (new OpenAiClient())->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);
    }

    public function test_transcribe_returns_text(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'tooth 13 has pulp necrosis'], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $text = (new OpenAiClient())->transcribe($audio);

        $this->assertSame('tooth 13 has pulp necrosis', $text);
    }
}
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/OpenAiClientTest.php`
Expected: FAIL — `Class "App\Services\OpenAiClient" not found`.

- [ ] **Step 5: Create `OpenAiClient`**

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpenAiClient
{
    public function chatCompletionJson(string $systemPrompt, string $userPrompt, array $jsonSchema): array
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'description' => ['OpenAI API key is not configured.'],
            ]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.chat_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI chat completion request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'description' => ['The AI service could not generate a treatment plan. Please try again.'],
            ]);
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'description' => ['The AI service returned an unreadable response.'],
            ]);
        }

        return $decoded;
    }

    public function transcribe(UploadedFile $audio): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'audio' => ['OpenAI API key is not configured.'],
            ]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName())
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => (string) config('services.openai.whisper_model', 'whisper-1'),
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI transcription request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'audio' => ['The AI service could not transcribe the recording. Please try again.'],
            ]);
        }

        return (string) $response->json('text');
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/OpenAiClientTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Services/OpenAiClient.php tests/Unit/Services/OpenAiClientTest.php config/services.php .env.example
git commit -m "feat: add OpenAiClient for chat completions and Whisper transcription"
```

---

### Task 4: `AiTreatmentPlanService` — JSON schema construction

**Files:**
- Create: `app/Services/AiTreatmentPlanService.php`
- Test: `tests/Unit/Services/AiTreatmentPlanServiceSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\AiTreatmentPlanService;
use Tests\TestCase;

class AiTreatmentPlanServiceSchemaTest extends TestCase
{
    public function test_build_json_schema_is_strict_and_lists_allowed_enums(): void
    {
        $schema = app(AiTreatmentPlanService::class)->buildJsonSchema();

        $this->assertTrue($schema['strict']);
        $this->assertSame('dental_treatment_plan', $schema['name']);

        $sessionSchema = $schema['schema']['properties']['sessions']['items'];
        $this->assertSame(['day_offset', 'duration_minutes', 'session_description', 'teeth'], $sessionSchema['required']);
        $this->assertFalse($sessionSchema['additionalProperties']);
        $this->assertSame(8, $schema['schema']['properties']['sessions']['maxItems']);

        $toothSchema = $sessionSchema['properties']['teeth']['items'];
        $this->assertContains('endo-filling', $toothSchema['properties']['endo']['enum']);
        $this->assertContains('amalgam', $toothSchema['properties']['filling_material']['enum']);
        $this->assertFalse($toothSchema['additionalProperties']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceSchemaTest.php`
Expected: FAIL — `Class "App\Services\AiTreatmentPlanService" not found`.

- [ ] **Step 3: Create `AiTreatmentPlanService` with `buildJsonSchema()`**

```php
<?php

namespace App\Services;

class AiTreatmentPlanService
{
    public function __construct(
        protected OpenAiClient $openAi,
        protected DoctorAvailabilityService $availability,
        protected AppointmentConflictService $conflicts,
    ) {
    }

    public function buildJsonSchema(): array
    {
        $enumOrNull = fn (array $values) => [
            'type' => ['string', 'null'],
            'enum' => [...$values, null],
        ];

        $toothSchema = [
            'type' => 'object',
            'properties' => [
                'tooth_number' => ['type' => 'integer', 'minimum' => 11, 'maximum' => 85],
                'tooth_selection' => $enumOrNull(OdontogramV2Vocabulary::toothSelection()),
                'crown_material' => $enumOrNull(OdontogramV2Vocabulary::crownMaterial()),
                'bridge_unit' => $enumOrNull(OdontogramV2Vocabulary::bridgeUnit()),
                'endo' => $enumOrNull(OdontogramV2Vocabulary::endo()),
                'filling_material' => $enumOrNull(OdontogramV2Vocabulary::fillingMaterial()),
                'filling_surfaces' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::fillingSurfaces()],
                ],
                'caries' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::caries()],
                ],
                'mods' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::mods()],
                ],
                'indicator_flags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => OdontogramV2Vocabulary::indicatorFlags()],
                ],
            ],
            'required' => [
                'tooth_number', 'tooth_selection', 'crown_material', 'bridge_unit', 'endo',
                'filling_material', 'filling_surfaces', 'caries', 'mods', 'indicator_flags',
            ],
            'additionalProperties' => false,
        ];

        $sessionSchema = [
            'type' => 'object',
            'properties' => [
                'day_offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 60],
                'duration_minutes' => ['type' => 'integer', 'enum' => [30, 60, 90]],
                'session_description' => ['type' => 'string'],
                'teeth' => [
                    'type' => 'array',
                    'items' => $toothSchema,
                    'minItems' => 0,
                    'maxItems' => 8,
                ],
            ],
            'required' => ['day_offset', 'duration_minutes', 'session_description', 'teeth'],
            'additionalProperties' => false,
        ];

        return [
            'name' => 'dental_treatment_plan',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'diagnosis_summary' => ['type' => 'string'],
                    'sessions' => [
                        'type' => 'array',
                        'items' => $sessionSchema,
                        'minItems' => 1,
                        'maxItems' => 8,
                    ],
                ],
                'required' => ['diagnosis_summary', 'sessions'],
                'additionalProperties' => false,
            ],
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceSchemaTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php tests/Unit/Services/AiTreatmentPlanServiceSchemaTest.php
git commit -m "feat: add AiTreatmentPlanService with the constrained OpenAI JSON schema"
```

---

### Task 5: `AiTreatmentPlanService::resolveSessionSlot()`

**Files:**
- Modify: `app/Services/AiTreatmentPlanService.php`
- Test: `tests/Unit/Services/AiTreatmentPlanServiceSlotTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AiTreatmentPlanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTreatmentPlanServiceSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_session_slot_finds_same_day_when_free(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        $monday = Carbon::now()->next(Carbon::MONDAY);

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $monday, 30);

        $this->assertSame($monday->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }

    public function test_resolve_session_slot_rolls_forward_when_day_is_fully_booked(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        $firstMonday = Carbon::now()->next(Carbon::MONDAY);
        $secondMonday = $firstMonday->copy()->addWeek();

        Appointment::create([
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'status' => 'scheduled',
            'date' => $firstMonday->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
        ]);

        $slot = app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $firstMonday, 30);

        $this->assertSame($secondMonday->toDateString(), $slot['date']);
        $this->assertSame('09:00', $slot['start_time']);
    }

    public function test_resolve_session_slot_throws_when_nothing_found_within_the_search_window(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => 'monday']);

        // A doctor who only works Mondays and only has one 30-minute slot: booking every
        // Monday for the next 3 weeks exhausts a 14-day search window (2 Mondays).
        $firstMonday = Carbon::now()->next(Carbon::MONDAY);
        foreach ([0, 1] as $weeksToAdd) {
            Appointment::create([
                'doctor_id' => $doctor->id,
                'type' => 'unavailable',
                'status' => 'scheduled',
                'date' => $firstMonday->copy()->addWeeks($weeksToAdd)->toDateString(),
                'start_time' => '09:00',
                'duration_minutes' => 30,
                'end_time' => '09:30',
            ]);
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AiTreatmentPlanService::class)->resolveSessionSlot($doctor, $firstMonday, 30, 14);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceSlotTest.php`
Expected: FAIL — `Call to undefined method App\Services\AiTreatmentPlanService::resolveSessionSlot()`.

- [ ] **Step 3: Add `resolveSessionSlot()` to `AiTreatmentPlanService`**

Add to `app/Services/AiTreatmentPlanService.php` (add the `use` statements at the top and the method inside the class):

```php
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
```

```php
    public function resolveSessionSlot(mixed $doctor, Carbon $fromDate, int $durationMinutes, int $searchDays = 14): array
    {
        $cursor = $fromDate->copy();

        for ($attempt = 0; $attempt < $searchDays; $attempt++) {
            try {
                $times = $this->availability->availableStartTimes($doctor, $cursor->toDateString(), $durationMinutes);

                if (! empty($times['start_times'])) {
                    return [
                        'date' => $cursor->toDateString(),
                        'start_time' => $times['start_times'][0],
                    ];
                }
            } catch (ValidationException) {
                // Doctor has no schedule for this weekday — try the next day.
            }

            $cursor->addDay();
        }

        throw ValidationException::withMessages([
            'sessions' => ["No available slot found for the doctor within {$searchDays} days starting from {$fromDate->toDateString()}."],
        ]);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceSlotTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php tests/Unit/Services/AiTreatmentPlanServiceSlotTest.php
git commit -m "feat: resolve nearest available appointment slot for AI treatment sessions"
```

---

### Task 6: Odontogram status mapping and the visit-summary envelope

**Files:**
- Modify: `app/Services/AiTreatmentPlanService.php`
- Test: `tests/Unit/Services/AiTreatmentPlanServiceOdontogramTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\AiTreatmentPlanService;
use Tests\TestCase;

class AiTreatmentPlanServiceOdontogramTest extends TestCase
{
    public function test_build_odontogram_status_maps_ai_fields_to_widget_shape(): void
    {
        $status = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            [
                'tooth_number' => 13,
                'tooth_selection' => null,
                'crown_material' => null,
                'bridge_unit' => null,
                'endo' => 'endo-filling-incomplete',
                'filling_material' => null,
                'filling_surfaces' => [],
                'caries' => [],
                'mods' => [],
                'indicator_flags' => ['pulpInflam'],
            ],
        ]);

        $this->assertSame('1.3', $status['version']);
        $this->assertSame('endo-filling-incomplete', $status['teeth']['13']['endo']);
        $this->assertTrue($status['teeth']['13']['pulpInflam']);
        $this->assertArrayNotHasKey('crownMaterial', $status['teeth']['13']);
    }

    public function test_build_planned_summary_matches_the_frontend_visit_odontogram_envelope(): void
    {
        $json = app(AiTreatmentPlanService::class)->buildPlannedSummary([
            'version' => '1.3',
            'globals' => [],
            'teeth' => ['13' => ['endo' => 'endo-filling-incomplete']],
        ]);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['__visit_odontogram__']);
        $this->assertSame(2, $decoded['companyVersion']);
        $this->assertSame([], $decoded['selectedTeeth']);
        $this->assertSame('endo-filling-incomplete', $decoded['odontogramV2Status']['teeth']['13']['endo']);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceOdontogramTest.php`
Expected: FAIL — undefined methods `buildOdontogramStatus()` and `buildPlannedSummary()`.

- [ ] **Step 3: Add both methods to `AiTreatmentPlanService`**

```php
    public function buildOdontogramStatus(array $teeth): array
    {
        $status = [
            'version' => '1.3',
            'globals' => [
                'wisdomVisible' => true,
                'showBase' => true,
                'occlusalVisible' => true,
                'showHealthyPulp' => true,
                'edentulous' => false,
            ],
            'teeth' => [],
        ];

        $fieldMap = [
            'tooth_selection' => 'toothSelection',
            'crown_material' => 'crownMaterial',
            'bridge_unit' => 'bridgeUnit',
            'endo' => 'endo',
            'filling_material' => 'fillingMaterial',
        ];

        foreach ($teeth as $tooth) {
            $toothNo = (string) $tooth['tooth_number'];
            $state = [];

            foreach ($fieldMap as $aiField => $widgetField) {
                if (! empty($tooth[$aiField])) {
                    $state[$widgetField] = $tooth[$aiField];
                }
            }

            if (! empty($tooth['filling_surfaces'])) {
                $state['fillingSurfaces'] = array_values($tooth['filling_surfaces']);
            }

            if (! empty($tooth['caries'])) {
                $state['caries'] = array_values($tooth['caries']);
            }

            if (! empty($tooth['mods'])) {
                $state['mods'] = array_values($tooth['mods']);
            }

            foreach ($tooth['indicator_flags'] ?? [] as $flag) {
                $state[$flag] = true;
            }

            $status['teeth'][$toothNo] = $state;
        }

        return $status;
    }

    public function buildPlannedSummary(array $odontogramStatus): string
    {
        return json_encode([
            '__visit_odontogram__' => true,
            'companyVersion' => 2,
            'activeTreatment' => 'consultation',
            'selectedTeeth' => [],
            'odontogramV2Status' => $odontogramStatus,
            'odontogramV2PricingOverrides' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/AiTreatmentPlanServiceOdontogramTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php tests/Unit/Services/AiTreatmentPlanServiceOdontogramTest.php
git commit -m "feat: translate AI tooth data into the Odontogram v2 widget shape"
```

---

### Task 7: Preview endpoint

**Files:**
- Create: `app/Http/Requests/AiTreatmentPlan/PreviewAiTreatmentPlanRequest.php`
- Create: `app/Http/Controllers/Api/AiTreatmentPlanController.php`
- Modify: `app/Services/AiTreatmentPlanService.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`

- [ ] **Step 1: Write the failing feature tests**

```php
<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreviewAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeOpenAiResponse(?array $sessions = null): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Pulp necrosis on tooth 13.',
                        'sessions' => $sessions ?? [
                            [
                                'day_offset' => 0,
                                'duration_minutes' => 30,
                                'session_description' => 'Open the canal and clean it.',
                                'teeth' => [
                                    [
                                        'tooth_number' => 13,
                                        'tooth_selection' => null,
                                        'crown_material' => null,
                                        'bridge_unit' => null,
                                        'endo' => 'endo-filling-incomplete',
                                        'filling_material' => null,
                                        'filling_surfaces' => [],
                                        'caries' => [],
                                        'mods' => [],
                                        'indicator_flags' => ['pulpInflam'],
                                    ],
                                ],
                            ],
                        ],
                    ])]],
                ],
            ], 200),
        ]);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function makeClient(): Client
    {
        return Client::create([
            'client_code' => 'CL-3001',
            'name' => 'Sami',
            'phone' => '+963900003001',
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    public function test_it_returns_a_draft_plan_without_persisting_anything(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertOk();

        $response->assertJsonPath('data.diagnosis_summary', 'Pulp necrosis on tooth 13.')
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.duration_minutes', 30)
            ->assertJsonPath('data.sessions.0.odontogram_v2_status.teeth.13.endo', 'endo-filling-incomplete');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_it_caps_sessions_at_eight_even_if_the_model_returns_more(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $extraSessions = array_fill(0, 10, [
            'day_offset' => 1,
            'duration_minutes' => 30,
            'session_description' => 'Follow-up.',
            'teeth' => [],
        ]);
        $this->fakeOpenAiResponse($extraSessions);

        $response = $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Multiple issues.',
        ])->assertOk();

        $response->assertJsonCount(8, 'data.sessions');
    }

    public function test_it_rejects_non_doctor_users(): void
    {
        $user = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($user);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422);
    }

    public function test_it_requires_a_description_or_audio(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`
Expected: FAIL — 404 (route doesn't exist yet).

- [ ] **Step 3: Add `preview()` to `AiTreatmentPlanService`**

Add to `app/Services/AiTreatmentPlanService.php`:

```php
    public function preview(mixed $doctor, string $description): array
    {
        $result = $this->openAi->chatCompletionJson(
            $this->buildSystemPrompt(),
            $description,
            $this->buildJsonSchema()
        );

        $sessions = [];
        $cursor = Carbon::now()->startOfDay();

        foreach (array_slice($result['sessions'], 0, 8) as $session) {
            $cursor = $cursor->copy()->addDays((int) $session['day_offset']);
            $slot = $this->resolveSessionSlot($doctor, $cursor, (int) $session['duration_minutes']);
            $cursor = Carbon::parse($slot['date']);

            $sessions[] = [
                'date' => $slot['date'],
                'start_time' => $slot['start_time'],
                'duration_minutes' => (int) $session['duration_minutes'],
                'session_description' => $session['session_description'],
                'odontogram_v2_status' => $this->buildOdontogramStatus($session['teeth']),
            ];
        }

        return [
            'diagnosis_summary' => $result['diagnosis_summary'],
            'sessions' => $sessions,
        ];
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are a dental treatment planning assistant used inside a clinic's patient
            record system. A doctor will describe a patient's dental condition in free
            text, possibly naming one or more tooth numbers (FDI notation, 11-85) and
            symptoms.

            Produce a treatment plan made of one or more future sessions (visits), each
            separated by a number of days from the previous one (day_offset; use 0 for
            the very first session, meaning "as soon as possible"). For each session,
            decide a realistic appointment duration (30, 60, or 90 minutes) and describe
            in session_description, in the same language the doctor used, what the
            doctor will do during that specific session.

            For each session, list the teeth involved and their condition/treatment
            using only the allowed vocabulary provided by the schema. If a tooth's
            condition does not map to any allowed value, leave that field null and
            mention the detail in session_description instead of guessing an
            unsupported value.

            Keep plans realistic: most common dental procedures need between 1 and 4
            sessions. Never propose more than 8 sessions.
            PROMPT;
    }
```

Add `use Illuminate\Support\Str;` is not needed; but make sure `Carbon` is imported (already added in Task 5).

- [ ] **Step 4: Create `PreviewAiTreatmentPlanRequest`**

```php
<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAiTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => [Rule::requiredIf(fn () => ! $this->hasFile('audio')), 'nullable', 'string', 'max:2000'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg', 'max:20480'],
        ];
    }
}
```

- [ ] **Step 5: Create `AiTreatmentPlanController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiTreatmentPlan\PreviewAiTreatmentPlanRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\AiTreatmentPlanService;
use App\Services\OpenAiClient;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanController extends Controller
{
    public function __construct(protected AiTreatmentPlanService $plans, protected OpenAiClient $openAi)
    {
    }

    public function preview(PreviewAiTreatmentPlanRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);

        $description = (string) ($request->validated('description') ?? '');

        if ($request->hasFile('audio')) {
            $description = $this->openAi->transcribe($request->file('audio'));
        }

        $plan = $this->plans->preview($doctor, $description);

        return $this->success($plan, 'AI treatment plan generated successfully.');
    }

    protected function assertIsDoctor(User $user): void
    {
        if (! $user->is_doctor) {
            throw ValidationException::withMessages([
                'description' => ['Only doctors can use the AI treatment assistant.'],
            ]);
        }
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/api.php`, add the import:

```php
use App\Http\Controllers\Api\AiTreatmentPlanController;
```

And add, right after the `clients/{client}/appointments` route:

```php
    Route::post('clients/{client}/ai-treatment-plan', [AiTreatmentPlanController::class, 'preview']);
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`
Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php app/Http/Requests/AiTreatmentPlan/PreviewAiTreatmentPlanRequest.php app/Http/Controllers/Api/AiTreatmentPlanController.php routes/api.php tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php
git commit -m "feat: add AI treatment plan preview endpoint"
```

---

### Task 8: Confirm endpoint

**Files:**
- Create: `app/Http/Requests/AiTreatmentPlan/ConfirmAiTreatmentPlanRequest.php`
- Modify: `app/Services/AiTreatmentPlanService.php`
- Modify: `app/Http/Controllers/Api/AiTreatmentPlanController.php`
- Modify: `app/Http/Resources/AppointmentResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AiTreatmentPlan/ConfirmAiTreatmentPlanTest.php`

- [ ] **Step 1: Write the failing feature tests**

```php
<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmAiTreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function makeClient(string $code = 'CL-3101'): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Rama',
            'phone' => '+963900003101',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    protected function sessionPayload(string $date): array
    {
        return [
            'date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'session_description' => 'Open the canal and clean it.',
            'odontogram_v2_status' => json_encode([
                'version' => '1.3',
                'globals' => [],
                'teeth' => ['13' => ['endo' => 'endo-filling-incomplete']],
            ]),
            'image' => UploadedFile::fake()->create('session-1.png', 10, 'image/png'),
        ];
    }

    public function test_it_creates_appointments_with_the_planned_data(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $response = $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $appointmentId = $response->json('data.0.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'status' => 'scheduled',
        ]);

        $appointment = Appointment::findOrFail($appointmentId);
        $this->assertSame('Open the canal and clean it.', $appointment->planned_notes);
        $this->assertStringContainsString('endo-filling-incomplete', $appointment->planned_summary);
        $this->assertNotNull($appointment->planned_image_path);
        Storage::disk('public')->assertExists($appointment->planned_image_path);

        $this->assertSame('Open the canal and clean it.', $response->json('data.0.planned_notes'));
        $this->assertNotNull($response->json('data.0.planned_image_url'));
    }

    public function test_it_rejects_the_whole_confirmation_if_any_session_conflicts(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3102');
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $this->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'type' => 'unavailable',
            'date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 30,
        ])->assertCreated();

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [$this->sessionPayload($date)],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_it_validates_session_shape(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-3103');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan/confirm", [
            'sessions' => [],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sessions');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/AiTreatmentPlan/ConfirmAiTreatmentPlanTest.php`
Expected: FAIL — 404 (route doesn't exist yet).

- [ ] **Step 3: Add `confirm()` to `AiTreatmentPlanService`**

Add to `app/Services/AiTreatmentPlanService.php` (add `use App\Enums\AppointmentStatus;`, `use App\Enums\AppointmentType;`, `use App\Models\Appointment;`, `use App\Models\Client;`, `use Illuminate\Support\Collection;`, `use Illuminate\Support\Facades\DB;` to the top):

```php
    public function confirm(Client $client, mixed $doctor, array $sessions, int $userId): Collection
    {
        foreach ($sessions as $session) {
            $this->conflicts->assertWithinSchedule($doctor, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
            $this->conflicts->assertNoConflict($doctor->id, $session['date'], $session['start_time'], (int) $session['duration_minutes']);
        }

        return DB::transaction(function () use ($client, $doctor, $sessions, $userId) {
            return collect($sessions)->map(function (array $session) use ($client, $doctor, $userId) {
                $odontogramStatus = json_decode((string) $session['odontogram_v2_status'], true);

                if (! is_array($odontogramStatus)) {
                    throw ValidationException::withMessages([
                        'sessions' => ['One of the sessions has an invalid odontogram payload.'],
                    ]);
                }

                $appointment = Appointment::create([
                    'client_id' => $client->id,
                    'doctor_id' => $doctor->id,
                    'type' => AppointmentType::Booked->value,
                    'status' => AppointmentStatus::Scheduled->value,
                    'date' => $session['date'],
                    'start_time' => $session['start_time'],
                    'duration_minutes' => (int) $session['duration_minutes'],
                    'end_time' => $this->conflicts->calculateEndTime($session['start_time'], (int) $session['duration_minutes']),
                    'planned_notes' => $session['session_description'],
                    'planned_summary' => $this->buildPlannedSummary($odontogramStatus),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                if (! empty($session['image'])) {
                    $path = $session['image']->storeAs('odontogram-plans', $appointment->uuid.'.png', 'public');
                    $appointment->update(['planned_image_path' => $path]);
                }

                return $appointment->fresh();
            });
        });
    }
```

- [ ] **Step 4: Create `ConfirmAiTreatmentPlanRequest`**

```php
<?php

namespace App\Http\Requests\AiTreatmentPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmAiTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sessions' => ['required', 'array', 'min:1', 'max:8'],
            'sessions.*.date' => ['required', 'date'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.duration_minutes' => ['required', 'integer', Rule::in([30, 60, 90])],
            'sessions.*.session_description' => ['required', 'string'],
            'sessions.*.odontogram_v2_status' => ['required', 'string'],
            'sessions.*.image' => ['required', 'file', 'mimes:png', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 5: Add `confirm()` to `AiTreatmentPlanController`**

Add to `app/Http/Controllers/Api/AiTreatmentPlanController.php` (add the imports and method):

```php
use App\Http\Requests\AiTreatmentPlan\ConfirmAiTreatmentPlanRequest;
use App\Http\Resources\AppointmentResource;
```

```php
    public function confirm(ConfirmAiTreatmentPlanRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);

        $appointments = $this->plans->confirm($client, $doctor, $request->validated('sessions'), $doctor->id);

        return $this->success(AppointmentResource::collection($appointments), 'Treatment plan confirmed and appointments created.', 201);
    }
```

- [ ] **Step 6: Expose planned fields on `AppointmentResource`**

In `app/Http/Resources/AppointmentResource.php`, add the import:

```php
use Illuminate\Support\Facades\Storage;
```

And add inside the returned array (after `'notes' => $this->notes,`):

```php
            'planned_summary' => $this->planned_summary,
            'planned_notes' => $this->planned_notes,
            'planned_image_url' => $this->planned_image_path ? Storage::disk('public')->url($this->planned_image_path) : null,
```

- [ ] **Step 7: Add the confirm route**

In `routes/api.php`, right after the preview route added in Task 7:

```php
    Route::post('clients/{client}/ai-treatment-plan/confirm', [AiTreatmentPlanController::class, 'confirm']);
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/AiTreatmentPlan/ConfirmAiTreatmentPlanTest.php`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php app/Http/Requests/AiTreatmentPlan/ConfirmAiTreatmentPlanRequest.php app/Http/Controllers/Api/AiTreatmentPlanController.php app/Http/Resources/AppointmentResource.php routes/api.php tests/Feature/AiTreatmentPlan/ConfirmAiTreatmentPlanTest.php
git commit -m "feat: add AI treatment plan confirm endpoint"
```

---

### Task 9: Carry the AI plan into the Visit at check-in

**Files:**
- Modify: `app/Http/Controllers/Api/ClientVisitController.php`
- Test: `tests/Feature/AiTreatmentPlan/CheckInAppliesPlannedDataTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckInAppliesPlannedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function doctorForToday(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ])->workingDays()->create(['weekday' => strtolower(now()->format('l'))]);

        return $doctor;
    }

    protected function makeClient(string $code): Client
    {
        return Client::create([
            'client_code' => $code,
            'name' => 'Nour',
            'phone' => '+963900004001',
            'gender' => 'female',
            'status' => 'new',
        ]);
    }

    public function test_check_in_defaults_visit_fields_from_the_appointments_ai_plan(): void
    {
        $doctor = $this->doctorForToday();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-4001');

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
            'planned_summary' => json_encode([
                '__visit_odontogram__' => true,
                'companyVersion' => 2,
                'odontogramV2Status' => ['teeth' => ['13' => ['endo' => 'endo-filling-incomplete']]],
            ]),
            'planned_notes' => 'Open the canal and clean it.',
            'planned_image_path' => 'odontogram-plans/example.png',
        ]);

        $this->postJson("/api/appointments/{$appointment->id}/check-in", [])->assertOk();

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointment->id,
            'notes' => 'Open the canal and clean it.',
            'odontogram_image_path' => 'odontogram-plans/example.png',
        ]);

        $visit = $appointment->visit()->firstOrFail();
        $this->assertStringContainsString('endo-filling-incomplete', $visit->summary);
    }

    public function test_check_in_lets_the_doctor_override_the_ai_plan(): void
    {
        $doctor = $this->doctorForToday();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient('CL-4002');

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => 'booked',
            'status' => 'scheduled',
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'end_time' => '09:30',
            'planned_notes' => 'AI suggested notes.',
        ]);

        $this->postJson("/api/appointments/{$appointment->id}/check-in", [
            'notes' => 'Doctor wrote something different.',
        ])->assertOk();

        $this->assertDatabaseHas('visits', [
            'appointment_id' => $appointment->id,
            'notes' => 'Doctor wrote something different.',
        ]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/AiTreatmentPlan/CheckInAppliesPlannedDataTest.php`
Expected: FAIL — `notes` on the created visit is `null`, not the planned value.

- [ ] **Step 3: Update `checkIn()`**

In `app/Http/Controllers/Api/ClientVisitController.php`, change:

```php
            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'summary' => $request->validated('summary'),
                'notes' => $request->validated('notes'),
                'attendance_status' => AttendanceStatus::Attended->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
```

to:

```php
            $visit = $appointment->visit()->create([
                'client_id' => $appointment->client_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->date,
                'start_time' => $appointment->start_time,
                'duration_minutes' => $appointment->duration_minutes,
                'summary' => $request->validated('summary') ?? $appointment->planned_summary,
                'notes' => $request->validated('notes') ?? $appointment->planned_notes,
                'odontogram_image_path' => $appointment->planned_image_path,
                'attendance_status' => AttendanceStatus::Attended->value,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/AiTreatmentPlan/CheckInAppliesPlannedDataTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/ClientVisitController.php tests/Feature/AiTreatmentPlan/CheckInAppliesPlannedDataTest.php
git commit -m "feat: carry the AI treatment plan into the visit at check-in"
```

---

### Task 10: Full suite run and wrap-up

**Files:** none (verification only)

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: all tests pass (existing tests plus every test added in Tasks 1-9).

- [ ] **Step 2: Run Pint to check code style**

Run: `./vendor/bin/pint --test`
Expected: no style violations. If there are, run `./vendor/bin/pint` to fix them and re-run `php artisan test`.

- [ ] **Step 3: Manually verify `storage:link` exists for local/dev environments**

Run: `php artisan storage:link`
Expected: either creates the `public/storage` symlink, or reports it already exists. This is required so `planned_image_url`/`odontogram_image_url` are actually reachable over HTTP outside of tests (tests use `Storage::fake()` and don't need the real symlink).

- [ ] **Step 4: Commit any style fixes, if Step 2 made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting"
```

(Skip this step if Pint made no changes.)
