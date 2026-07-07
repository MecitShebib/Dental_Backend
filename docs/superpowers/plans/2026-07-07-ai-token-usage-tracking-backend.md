# AI Token Usage Tracking — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Track OpenAI Chat Completions token usage (input + output) per company, cap it against a per-subscription `max_ai_tokens` allowance, block further AI treatment-plan requests once the cap is hit, and expose the running total through the existing subscription API and admin panel.

**Architecture:** Two additive migrations extend `subscriptions` with `max_ai_tokens`/`ai_tokens_used` and add a new `ai_usage_logs` audit table. A new `AiTokenUsageService` (mirroring the existing `CompanyUserLimitService` pattern) exposes `assertCanUseAiTokens()` (called before any OpenAI call) and `recordUsage()` (called after a successful chat completion). `OpenAiClient::chatCompletionJson()` starts returning OpenAI's `usage` block alongside the decoded content; `AiTreatmentPlanService::preview()` bubbles it up; `AiTreatmentPlanController::preview()` wires the assert/record calls around the existing OpenAI calls.

**Tech Stack:** Laravel 12, Sanctum, PHPUnit (existing `tests/Feature` and `tests/Unit` split), SQLite in-memory for tests, Laravel `Http` facade with `Http::fake()`.

**Spec:** `docs/superpowers/specs/2026-07-07-ai-token-usage-tracking-design.md` — read this first for the "why" behind each decision (per-subscription reset, hard block, Whisper excluded, incremental counter).

This plan covers the backend only. A separate frontend plan will wire the `Dental_FrontEnd` subscription page and AI treatment-plan modal to the fields this plan adds to the API — see design §11.

---

## File Structure

**New files:**
- `database/migrations/2026_07_07_100000_add_ai_token_fields_to_subscriptions_table.php`
- `database/migrations/2026_07_07_100100_create_ai_usage_logs_table.php`
- `app/Models/AiUsageLog.php`
- `app/Services/AiTokenUsageService.php`
- `tests/Unit/Services/AiTokenUsageServiceTest.php`
- `tests/Feature/CompanySubscriptionsApiTest.php`

**Modified files:**
- `app/Models/Subscription.php` — add `max_ai_tokens`, `ai_tokens_used` to `$fillable`/casts.
- `app/Models/Company.php` — add `aiUsageLogs(): HasMany`.
- `app/Services/OpenAiClient.php` — `chatCompletionJson()` returns `['content' => ..., 'usage' => ...]`.
- `app/Services/AiTreatmentPlanService.php` — `preview()` bubbles `usage` up in its return array.
- `app/Http/Controllers/Api/AiTreatmentPlanController.php` — inject `AiTokenUsageService`, assert before/record after the OpenAI calls.
- `app/Http/Resources/SubscriptionResource.php` — expose `max_ai_tokens`, `ai_tokens_used`.
- `app/Http/Requests/Subscription/StoreSubscriptionRequest.php` — add `max_ai_tokens` validation rule.
- `app/Http/Requests/Subscription/UpdateSubscriptionRequest.php` — add `max_ai_tokens` validation rule.
- `resources/views/admin/subscriptions/index.blade.php` — add the `max_ai_tokens` field to both forms and the list.
- `tests/Unit/Services/OpenAiClientTest.php` — update for the new `chatCompletionJson()` return shape.
- `tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php` — give test doctors an active subscription; add token-limit-blocking and usage-recording tests.

---

### Task 1: Migrations, `AiUsageLog` model, and `Subscription`/`Company` model updates

**Files:**
- Create: `database/migrations/2026_07_07_100000_add_ai_token_fields_to_subscriptions_table.php`
- Create: `database/migrations/2026_07_07_100100_create_ai_usage_logs_table.php`
- Create: `app/Models/AiUsageLog.php`
- Modify: `app/Models/Subscription.php`
- Modify: `app/Models/Company.php`

- [ ] **Step 1: Create the subscriptions migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('max_ai_tokens')->nullable()->after('active_users');
            $table->unsignedBigInteger('ai_tokens_used')->default(0)->after('max_ai_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['max_ai_tokens', 'ai_tokens_used']);
        });
    }
};
```

- [ ] **Step 2: Create the `ai_usage_logs` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamps();

            $table->index('company_id');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
```

- [ ] **Step 3: Run the migrations against the local dev database**

Run: `php artisan migrate`
Expected: both new migrations run with no errors.

- [ ] **Step 4: Create the `AiUsageLog` model**

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'subscription_id',
        'user_id',
        'client_id',
        'action',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 5: Add the new columns to `Subscription::$fillable` and casts**

In `app/Models/Subscription.php`, change:

```php
    protected $fillable = [
        'company_id',
        'plan_name',
        'status',
        'starts_at',
        'ends_at',
        'max_users',
        'active_users',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_users' => 'integer',
            'active_users' => 'integer',
            'price' => 'decimal:2',
        ];
    }
```

to:

```php
    protected $fillable = [
        'company_id',
        'plan_name',
        'status',
        'starts_at',
        'ends_at',
        'max_users',
        'active_users',
        'max_ai_tokens',
        'ai_tokens_used',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_users' => 'integer',
            'active_users' => 'integer',
            'max_ai_tokens' => 'integer',
            'ai_tokens_used' => 'integer',
            'price' => 'decimal:2',
        ];
    }
```

- [ ] **Step 6: Add the `aiUsageLogs()` relation to `Company`**

In `app/Models/Company.php`, add this method right after `treatmentCatalog()`:

```php
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }
```

(`HasMany` is already imported at the top of this file.)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_07_100000_add_ai_token_fields_to_subscriptions_table.php database/migrations/2026_07_07_100100_create_ai_usage_logs_table.php app/Models/AiUsageLog.php app/Models/Subscription.php app/Models/Company.php
git commit -m "feat: add ai_tokens columns and ai_usage_logs table"
```

---

### Task 2: `AiTokenUsageService`

**Files:**
- Create: `app/Services/AiTokenUsageService.php`
- Test: `tests/Unit/Services/AiTokenUsageServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AiTokenUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiTokenUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function activeSubscription(Company $company, ?int $maxAiTokens, int $aiTokensUsed = 0): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => $maxAiTokens,
            'ai_tokens_used' => $aiTokensUsed,
        ]);
    }

    public function test_assert_can_use_ai_tokens_passes_when_unlimited(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, null, 999999);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);

        $this->assertTrue(true);
    }

    public function test_assert_can_use_ai_tokens_passes_when_under_the_limit(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, 1000, 500);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);

        $this->assertTrue(true);
    }

    public function test_assert_can_use_ai_tokens_throws_when_at_the_limit(): void
    {
        $company = Company::factory()->create();
        $this->activeSubscription($company, 1000, 1000);

        $this->expectException(ValidationException::class);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
    }

    public function test_assert_can_use_ai_tokens_throws_when_there_is_no_active_subscription(): void
    {
        $company = Company::factory()->create();

        $this->expectException(ValidationException::class);

        app(AiTokenUsageService::class)->assertCanUseAiTokens($company);
    }

    public function test_record_usage_increments_the_subscription_counter_and_creates_a_log_row(): void
    {
        $company = Company::factory()->create();
        $subscription = $this->activeSubscription($company, 1000, 100);
        $user = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = Client::create([
            'client_code' => 'CL-9001',
            'name' => 'Test Client',
            'phone' => '+963900009001',
            'gender' => 'male',
            'status' => 'new',
        ]);

        app(AiTokenUsageService::class)->recordUsage(
            $company,
            $user,
            $client,
            'ai_treatment_plan_preview',
            'gpt-4o-mini',
            120,
            80
        );

        $this->assertSame(300, $subscription->fresh()->ai_tokens_used);
        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_preview',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
        ]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/AiTokenUsageServiceTest.php`
Expected: FAIL — `Class "App\Services\AiTokenUsageService" not found`.

- [ ] **Step 3: Create `AiTokenUsageService`**

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiTokenUsageService
{
    public function assertCanUseAiTokens(Company $company): void
    {
        $subscription = $company->currentSubscription()->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'ai_tokens' => ['This company does not have an active subscription.'],
            ]);
        }

        if ($subscription->max_ai_tokens !== null && $subscription->ai_tokens_used >= $subscription->max_ai_tokens) {
            throw ValidationException::withMessages([
                'ai_tokens' => ['The AI token usage limit for this subscription has been reached. Please raise the limit or upgrade the subscription.'],
            ]);
        }
    }

    public function recordUsage(
        Company $company,
        User $user,
        ?Client $client,
        string $action,
        string $model,
        int $promptTokens,
        int $completionTokens
    ): void {
        DB::transaction(function () use ($company, $user, $client, $action, $model, $promptTokens, $completionTokens) {
            $subscription = $company->currentSubscription()->first();

            $company->aiUsageLogs()->create([
                'subscription_id' => $subscription?->id,
                'user_id' => $user->id,
                'client_id' => $client?->id,
                'action' => $action,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ]);

            $subscription?->increment('ai_tokens_used', $promptTokens + $completionTokens);
        });
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/AiTokenUsageServiceTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AiTokenUsageService.php tests/Unit/Services/AiTokenUsageServiceTest.php
git commit -m "feat: add AiTokenUsageService to gate and record AI token usage"
```

---

### Task 3: `OpenAiClient::chatCompletionJson()` returns token usage

**Files:**
- Modify: `app/Services/OpenAiClient.php`
- Modify: `tests/Unit/Services/OpenAiClientTest.php`

- [ ] **Step 1: Update the failing test**

In `tests/Unit/Services/OpenAiClientTest.php`, replace `test_chat_completion_json_returns_decoded_content` with:

```php
    public function test_chat_completion_json_returns_content_and_usage(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['diagnosis_summary' => 'ok', 'sessions' => []])]],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 80,
                    'total_tokens' => 200,
                ],
            ], 200),
        ]);

        $result = (new OpenAiClient)->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);

        $this->assertSame('ok', $result['content']['diagnosis_summary']);
        $this->assertSame(120, $result['usage']['prompt_tokens']);
        $this->assertSame(80, $result['usage']['completion_tokens']);
        $this->assertSame(200, $result['usage']['total_tokens']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['response_format']['type'] === 'json_schema';
        });
    }

    public function test_chat_completion_json_defaults_usage_to_zero_when_missing(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['diagnosis_summary' => 'ok', 'sessions' => []])]],
                ],
            ], 200),
        ]);

        $result = (new OpenAiClient)->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);

        $this->assertSame(0, $result['usage']['prompt_tokens']);
        $this->assertSame(0, $result['usage']['completion_tokens']);
        $this->assertSame(0, $result['usage']['total_tokens']);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/OpenAiClientTest.php`
Expected: FAIL — `Undefined array key "content"` (the method still returns the decoded plan directly, not wrapped).

- [ ] **Step 3: Update `chatCompletionJson()`**

In `app/Services/OpenAiClient.php`, change:

```php
        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'description' => ['The AI service returned an unreadable response.'],
            ]);
        }

        return $decoded;
    }
```

to:

```php
        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'description' => ['The AI service returned an unreadable response.'],
            ]);
        }

        return [
            'content' => $decoded,
            'usage' => [
                'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
                'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
                'total_tokens' => (int) $response->json('usage.total_tokens', 0),
            ],
        ];
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/OpenAiClientTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/OpenAiClient.php tests/Unit/Services/OpenAiClientTest.php
git commit -m "feat: return OpenAI token usage from chatCompletionJson"
```

---

### Task 4: Wire `AiTokenUsageService` into the preview endpoint

**Files:**
- Modify: `app/Services/AiTreatmentPlanService.php`
- Modify: `app/Http/Controllers/Api/AiTreatmentPlanController.php`
- Modify: `tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`

This is the vertical slice that makes the block/record behavior observable end-to-end, so it's driven by the feature test (the codebase's existing convention — there is no unit test that calls `AiTreatmentPlanService::preview()` directly; it's only exercised through `PreviewAiTreatmentPlanTest`).

- [ ] **Step 1: Update `PreviewAiTreatmentPlanTest.php`**

Replace the whole file with:

```php
<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    protected function fakeOpenAiResponse(?array $sessions = null, array $usage = ['prompt_tokens' => 120, 'completion_tokens' => 80, 'total_tokens' => 200]): void
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
                'usage' => $usage,
            ], 200),
        ]);
    }

    protected function activeSubscription(Company $company, ?int $maxAiTokens = null, int $aiTokensUsed = 0): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => $maxAiTokens,
            'ai_tokens_used' => $aiTokensUsed,
        ]);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company);

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
            ->assertJsonPath('data.sessions.0.odontogram_v2_status.teeth.13.endo', 'endo-filling-incomplete')
            ->assertJsonMissingPath('data.usage');

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
        ])->assertStatus(422)
            ->assertJsonValidationErrors('doctor');
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

    public function test_it_transcribes_audio_when_no_description_is_provided(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Tooth 13 has pulp necrosis.'], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'diagnosis_summary' => 'Pulp necrosis on tooth 13.',
                        'sessions' => [],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 30, 'total_tokens' => 80],
            ], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $this->post("/api/clients/{$client->id}/ai-treatment-plan", [
            'audio' => $audio,
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['messages'][1]['content'] === 'Tooth 13 has pulp necrosis.';
        });
    }

    public function test_it_records_ai_token_usage_after_a_successful_preview(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        $this->fakeOpenAiResponse();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertOk();

        $subscription = $doctor->company->currentSubscription()->first();
        $this->assertSame(200, $subscription->ai_tokens_used);

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $doctor->company_id,
            'subscription_id' => $subscription->id,
            'user_id' => $doctor->id,
            'client_id' => $client->id,
            'action' => 'ai_treatment_plan_preview',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 80,
            'total_tokens' => 200,
        ]);
    }

    public function test_it_blocks_preview_when_the_company_has_reached_its_ai_token_limit(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $this->activeSubscription($doctor->company, maxAiTokens: 100, aiTokensUsed: 100);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient();
        Http::fake();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan", [
            'description' => 'Tooth 13 has pulp necrosis.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('ai_tokens');

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the tests to verify the new ones fail**

Run: `php artisan test tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`
Expected: the two new tests FAIL — `test_it_records_ai_token_usage_after_a_successful_preview` fails because `ai_tokens_used` is still `0`; `test_it_blocks_preview_when_the_company_has_reached_its_ai_token_limit` fails because the request currently returns something other than a `422` with an `ai_tokens` error (no such check exists yet).

- [ ] **Step 3: Bubble `usage` through `AiTreatmentPlanService::preview()`**

In `app/Services/AiTreatmentPlanService.php`, change:

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
```

to:

```php
    public function preview(mixed $doctor, string $description): array
    {
        $response = $this->openAi->chatCompletionJson(
            $this->buildSystemPrompt(),
            $description,
            $this->buildJsonSchema()
        );

        $result = $response['content'];
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
            'usage' => $response['usage'],
        ];
    }
```

- [ ] **Step 4: Wire `AiTokenUsageService` into `AiTreatmentPlanController`**

In `app/Http/Controllers/Api/AiTreatmentPlanController.php`, change:

```php
use App\Services\AiTreatmentPlanService;
use App\Services\OpenAiClient;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanController extends Controller
{
    public function __construct(protected AiTreatmentPlanService $plans, protected OpenAiClient $openAi) {}

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
```

to:

```php
use App\Services\AiTokenUsageService;
use App\Services\AiTreatmentPlanService;
use App\Services\OpenAiClient;
use Illuminate\Validation\ValidationException;

class AiTreatmentPlanController extends Controller
{
    public function __construct(
        protected AiTreatmentPlanService $plans,
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
    ) {}

    public function preview(PreviewAiTreatmentPlanRequest $request, Client $client)
    {
        $doctor = $request->user();
        $this->assertIsDoctor($doctor);
        $this->aiTokenUsage->assertCanUseAiTokens($doctor->company);

        $description = (string) ($request->validated('description') ?? '');

        if ($request->hasFile('audio')) {
            $description = $this->openAi->transcribe($request->file('audio'));
        }

        $plan = $this->plans->preview($doctor, $description);
        $usage = $plan['usage'];
        unset($plan['usage']);

        $this->aiTokenUsage->recordUsage(
            $doctor->company,
            $doctor,
            $client,
            'ai_treatment_plan_preview',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $usage['prompt_tokens'],
            (int) $usage['completion_tokens'],
        );

        return $this->success($plan, 'AI treatment plan generated successfully.');
    }
```

(Leave `assertIsDoctor()` and `confirm()` below this untouched — `confirm()` doesn't call OpenAI, so it's out of scope for token tracking per the design.)

- [ ] **Step 5: Run the full test file to verify everything passes**

Run: `php artisan test tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php`
Expected: PASS (8 tests)

- [ ] **Step 6: Run the rest of the AI treatment plan suite to check for regressions**

Run: `php artisan test tests/Feature/AiTreatmentPlan tests/Unit/Services`
Expected: PASS — `ConfirmAiTreatmentPlanTest`, `CheckInAppliesPlannedDataTest`, and the odontogram/schema/slot unit tests are unaffected by this change (they don't call `preview()` or the controller).

- [ ] **Step 7: Commit**

```bash
git add app/Services/AiTreatmentPlanService.php app/Http/Controllers/Api/AiTreatmentPlanController.php tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php
git commit -m "feat: block and record AI token usage on the treatment plan preview endpoint"
```

---

### Task 5: Expose `max_ai_tokens`/`ai_tokens_used` on `SubscriptionResource`

**Files:**
- Modify: `app/Http/Resources/SubscriptionResource.php`
- Create: `tests/Feature/CompanySubscriptionsApiTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriptions_endpoint_exposes_ai_token_fields(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_ai_tokens' => 5000,
            'ai_tokens_used' => 1200,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();

        $response->assertJsonPath('data.0.max_ai_tokens', 5000)
            ->assertJsonPath('data.0.ai_tokens_used', 1200);
    }

    public function test_subscriptions_endpoint_exposes_null_max_ai_tokens_as_unlimited(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();

        $response->assertJsonPath('data.0.max_ai_tokens', null)
            ->assertJsonPath('data.0.ai_tokens_used', 0);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/CompanySubscriptionsApiTest.php`
Expected: FAIL — `data.0.max_ai_tokens` is missing from the response.

- [ ] **Step 3: Update `SubscriptionResource`**

In `app/Http/Resources/SubscriptionResource.php`, change:

```php
            'max_users' => $this->max_users,
            'active_users' => $this->active_users,
            'price' => (float) ($this->price ?? 0),
```

to:

```php
            'max_users' => $this->max_users,
            'active_users' => $this->active_users,
            'max_ai_tokens' => $this->max_ai_tokens,
            'ai_tokens_used' => $this->ai_tokens_used,
            'price' => (float) ($this->price ?? 0),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/CompanySubscriptionsApiTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/SubscriptionResource.php tests/Feature/CompanySubscriptionsApiTest.php
git commit -m "feat: expose max_ai_tokens and ai_tokens_used on SubscriptionResource"
```

---

### Task 6: Admin panel — set `max_ai_tokens` per subscription

**Files:**
- Modify: `app/Http/Requests/Subscription/StoreSubscriptionRequest.php`
- Modify: `app/Http/Requests/Subscription/UpdateSubscriptionRequest.php`
- Modify: `resources/views/admin/subscriptions/index.blade.php`

No automated tests exist today for the admin panel's controllers or views (there is no `tests/Feature/Admin` directory in this codebase), so this task is verified manually in Step 5 rather than introducing a new testing pattern for one field.

- [ ] **Step 1: Add the validation rule to `StoreSubscriptionRequest`**

In `app/Http/Requests/Subscription/StoreSubscriptionRequest.php`, change:

```php
            'max_users' => ['required', 'integer', 'min:1'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
```

to:

```php
            'max_users' => ['required', 'integer', 'min:1'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'max_ai_tokens' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
```

- [ ] **Step 2: Add the same rule to `UpdateSubscriptionRequest`**

In `app/Http/Requests/Subscription/UpdateSubscriptionRequest.php`, apply the identical change:

```php
            'max_users' => ['required', 'integer', 'min:1'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'max_ai_tokens' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
```

- [ ] **Step 3: Add the field to the create form**

In `resources/views/admin/subscriptions/index.blade.php`, change:

```blade
                <input type="number" min="1" name="max_users" placeholder="Max users" required>
                <input type="number" step="0.01" min="0" name="price" placeholder="Price">
```

to:

```blade
                <input type="number" min="1" name="max_users" placeholder="Max users" required>
                <input type="number" min="0" name="max_ai_tokens" placeholder="Max AI tokens (blank = unlimited)">
                <input type="number" step="0.01" min="0" name="price" placeholder="Price">
```

- [ ] **Step 4: Add the field to the list/edit view**

In the same file, change:

```blade
                            <td>
                                {{ $subscription->starts_at?->format('Y-m-d') }}<br>
                                <small>{{ $subscription->ends_at?->format('Y-m-d') ?? 'Open end' }}</small><br>
                                <small>{{ $subscription->active_users }}/{{ $subscription->max_users }} active users</small>
                            </td>
```

to:

```blade
                            <td>
                                {{ $subscription->starts_at?->format('Y-m-d') }}<br>
                                <small>{{ $subscription->ends_at?->format('Y-m-d') ?? 'Open end' }}</small><br>
                                <small>{{ $subscription->active_users }}/{{ $subscription->max_users }} active users</small><br>
                                <small>{{ $subscription->ai_tokens_used }}/{{ $subscription->max_ai_tokens ?? '∞' }} AI tokens</small>
                            </td>
```

and change:

```blade
                                    <input type="number" min="1" name="max_users" value="{{ $subscription->max_users }}" required>
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $subscription->price }}">
```

to:

```blade
                                    <input type="number" min="1" name="max_users" value="{{ $subscription->max_users }}" required>
                                    <input type="number" min="0" name="max_ai_tokens" value="{{ $subscription->max_ai_tokens }}" placeholder="Max AI tokens (blank = unlimited)">
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $subscription->price }}">
```

- [ ] **Step 5: Manually verify in the browser**

Run: `php artisan serve` (or `composer run dev`), then:
1. Log into the admin panel at `/admin/login` as a project-admin user.
2. Go to `/admin/subscriptions`.
3. Create a subscription leaving "Max AI tokens" blank — confirm it saves and the list shows `0/∞ AI tokens`.
4. Edit that subscription, set "Max AI tokens" to `1000` — confirm the list now shows `0/1000 AI tokens`.

Expected: both save without validation errors and the list reflects the values entered.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Subscription/StoreSubscriptionRequest.php app/Http/Requests/Subscription/UpdateSubscriptionRequest.php resources/views/admin/subscriptions/index.blade.php
git commit -m "feat: let admins set max_ai_tokens per subscription"
```

---

### Task 7: Full regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the entire backend test suite**

Run: `composer run test`
Expected: PASS, zero failures.

- [ ] **Step 2: Run Laravel Pint**

Run: `./vendor/bin/pint`
Expected: no files need formatting changes (or Pint auto-fixes minor style issues — re-run the test suite afterward if it changes anything).

- [ ] **Step 3: Confirm no leftover `usage` key reaches the API response**

Run: `php artisan test --filter=test_it_returns_a_draft_plan_without_persisting_anything -v`
Expected: PASS — this test's `assertJsonMissingPath('data.usage')` assertion (added in Task 4) confirms the internal `usage` array never leaks into the public API response.
