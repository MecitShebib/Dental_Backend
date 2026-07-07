# AI Token Usage Tracking — Design

Date: 2026-07-07
Repos affected: `Dental_Backend` (Laravel API + admin panel) and `Dental_FrontEnd` (React/Vite)

## 1. Problem & Goal

The AI treatment plan assistant (`AiTreatmentPlanService`/`OpenAiClient`) calls OpenAI's Chat
Completions API on every "preview" request, but the backend currently discards the `usage`
block OpenAI returns with each response — there is no record anywhere of how many tokens a
company's doctors have consumed. There is also no per-company cap: every company can call the
AI feature an unlimited number of times regardless of what they're paying for.

This feature adds token accounting so that each company's subscription carries a maximum AI
token allowance and a running used-token count (input + output tracked together as a
combined total), blocks further AI requests once that allowance is exhausted (requiring the
admin to raise the limit or issue a new subscription), and keeps a per-call audit log for
future reference. The current usage is also surfaced on the frontend's subscription info page.

## 2. Scope boundaries (explicit, to prevent scope creep)

- **In scope**: token accounting for the Chat Completions call inside
  `AiTreatmentPlanService::preview()` (the only chat-completion call site in the codebase);
  a hard block once a company's subscription-level cap is reached; an admin-editable cap field
  on the existing subscription create/edit form; an internal audit log table; a token-usage
  display + limit-reached warning on the frontend subscription page and inside the AI
  treatment-plan modal.
- **Out of scope**:
  - **Whisper (audio transcription) is not counted.** Whisper is priced per minute of audio,
    not per token, and its default response doesn't return a `usage` block at all. Tracking it
    would need a separate unit (seconds/minutes) and a separate cap, which is not requested now.
    The token cap still blocks the Whisper call too (see §5) — it just isn't the thing being
    measured.
  - **No admin-facing UI to browse `ai_usage_logs` row-by-row.** The log table exists purely so
    the aggregate counter has an auditable source and so a future admin view can be added
    without a schema change. Today only the aggregate `ai_tokens_used` counter is surfaced.
  - **No cost/currency conversion.** Only raw token counts are tracked, not a dollar estimate —
    `config/services.php` has no OpenAI pricing table today and adding one is a separate concern.
  - **No soft/grace overage.** Reaching the cap blocks immediately; there is no "allow 110% then
    warn" behavior.
  - **No usage reset endpoint/button.** The only way `ai_tokens_used` changes is by accruing
    usage or by the admin creating a new subscription record (which starts at 0). Raising the
    cap on the existing record does not zero out the counter.

## 3. Data model changes (Backend)

### `subscriptions` table (new migration, additive columns)

- `max_ai_tokens` — nullable unsigned big integer. `null` means **unlimited**, which is also
  the effective value for every pre-existing subscription row after migrating (no company is
  retroactively blocked by this rollout).
- `ai_tokens_used` — unsigned big integer, default `0`.

Both are added to `Subscription`'s `$fillable` and cast to integers, exactly like `max_users`/
`active_users` today. No reset job or scheduled task is needed: since the counter lives on the
subscription row itself, a brand-new subscription (created by the admin, e.g. on renewal)
starts at `ai_tokens_used = 0` for free, the same way `active_users` does today.

### New table `ai_usage_logs`

One row per successful Chat Completions call:

- `id`, `uuid` (via the codebase-wide `HasUuid` trait)
- `company_id` — FK → `companies`, cascade on delete
- `subscription_id` — FK → `subscriptions`, nullable, null on delete (the subscription active
  at the time of the call)
- `user_id` — FK → `users`, nullable, null on delete (the doctor who triggered the request)
- `client_id` — FK → `clients`, nullable, null on delete (the patient the plan was generated for)
- `action` — string, e.g. `ai_treatment_plan_preview` (kept generic/extensible for future AI
  features beyond the treatment plan assistant)
- `model` — string, e.g. `gpt-4o-mini` (from `config('services.openai.chat_model')` at call time)
- `prompt_tokens`, `completion_tokens`, `total_tokens` — unsigned integers
- `timestamps`
- Indexes on `company_id` and `subscription_id`

New `AiUsageLog` model (`app/Models/AiUsageLog.php`) with `HasUuid`, the fillable fields above,
and `belongsTo` relations to `Company`, `Subscription`, `User`, `Client`. `Company` gains an
`aiUsageLogs(): HasMany` relation.

## 4. Backend service — `AiTokenUsageService`

New service, mirroring the existing `CompanyUserLimitService` pattern (an `assertCan...()`
guard + a persistence method):

```
assertCanUseAiTokens(Company $company): void
```
- Loads `$company->currentSubscription()->first()`.
- Throws `ValidationException::withMessages(['ai_tokens' => [...]])` if there is no active
  subscription (defensive — login is already gated by `SubscriptionAccessService`, so this
  should not normally trigger).
- Throws the same exception shape if `max_ai_tokens !== null && ai_tokens_used >= max_ai_tokens`.

```
recordUsage(Company $company, User $user, ?Client $client, string $action, string $model,
            int $promptTokens, int $completionTokens): void
```
- Inside one `DB::transaction`: creates the `ai_usage_logs` row (`total_tokens` =
  `promptTokens + completionTokens`), then `increment()`s `ai_tokens_used` on the company's
  current subscription by the same total. If there is no current subscription at this point
  (edge case — subscription expired mid-request), the log row is still written with a null
  `subscription_id`, but no counter update happens.

## 5. Wiring into the existing AI call path

- **`OpenAiClient::chatCompletionJson()`** (`app/Services/OpenAiClient.php:12-57`) currently
  returns only the decoded plan array (line 47-56 reads `choices.0.message.content` and
  discards everything else in the response). It will instead return:
  ```php
  [
      'content' => $decoded,
      'usage' => [
          'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
          'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
          'total_tokens' => (int) $response->json('usage.total_tokens', 0),
      ],
  ]
  ```
  Missing/malformed `usage` defaults to zeros rather than failing the request — a
  treatment-plan generation should not fail just because usage reporting came back incomplete.
- **`AiTreatmentPlanService::preview()`** (`app/Services/AiTreatmentPlanService.php`) takes
  `AiTokenUsageService` as a 4th constructor dependency and a new `Client $client` parameter.
  Immediately after `chatCompletionJson()` returns — **before** the `foreach` loop that resolves
  each session's slot via `resolveSessionSlot()` — it calls `AiTokenUsageService::recordUsage(...)`
  with the prompt/completion token counts. This ordering is deliberate, not incidental: slot
  resolution can throw (`ValidationException`, e.g. no available slot within the search window)
  *after* the OpenAI call has already been made and billed. Recording usage before that loop
  means the company is charged the correct token count even when the overall preview request
  ultimately fails — the alternative (recording only after `preview()` fully returns, originally
  attempted and caught in review) silently drops usage accounting on every slot-resolution
  failure, which defeats the purpose of the feature. The method's return array no longer carries
  a `usage` key — recording happens internally, not via a value handed back to the controller.
- **`AiTreatmentPlanController::preview()`** (`app/Http/Controllers/Api/AiTreatmentPlanController.php`)
  calls `AiTokenUsageService::assertCanUseAiTokens($doctor->company)` **before** doing anything
  else — before the Whisper transcription call and before `AiTreatmentPlanService::preview()`.
  This means a company that has hit its cap incurs **zero** further OpenAI cost, including for
  Whisper (whose own tokens aren't tracked, but whose calls are still gated by the same check).
  It then simply calls `$this->plans->preview($doctor, $client, $description)` and passes the
  result straight to `$this->success(...)` — no post-processing needed, since usage recording
  already happened inside `preview()` itself.
- **`OpenAiClient::transcribe()`** (Whisper) is unchanged — no `usage` capture, per §2.

## 6. Admin panel

- `StoreSubscriptionRequest`/`UpdateSubscriptionRequest` gain
  `'max_ai_tokens' => ['nullable', 'integer', 'min:0']`.
- `resources/views/admin/subscriptions/index.blade.php`: a `max_ai_tokens` number input
  (placeholder "Max AI tokens (blank = unlimited)") is added next to the existing `max_users`
  input in both the create form and each row's edit form. The subscriptions list also shows
  `{{ ai_tokens_used }}/{{ max_ai_tokens ?? '∞' }} AI tokens` alongside the existing
  `active_users`/`max_users` line.
- `SubscriptionController::store()`/`update()` need no logic changes beyond the new field
  passing through `$request->validated()` — unlike `max_users`, there is **no** guard preventing
  `max_ai_tokens` from being set below the current `ai_tokens_used` (an admin may deliberately
  tighten a cap below current usage; the company simply stays blocked until raised again).

## 7. API exposure

`SubscriptionResource` gains `max_ai_tokens` and `ai_tokens_used` (raw values, same convention
as `max_users`/`active_users` — the frontend computes "remaining" and "is limit reached"
client-side rather than the backend precomputing it). `CompanyResource` needs no change since it
already nests `SubscriptionResource` via `latest_active_subscription`.

## 8. Frontend (`Dental_FrontEnd`)

- **`AppStateApiContext.jsx`**: `mapBackendSubscription` (~line 349-365) maps the two new
  fields (`maxAiTokens`, `aiTokensUsed`). Two new computed values sit next to the existing
  `remainingSeats`/`isSeatLimitReached` (~line 815-817):
  - `remainingAiTokens` = `maxAiTokens == null ? null : Math.max(maxAiTokens - aiTokensUsed, 0)`
  - `isAiTokenLimitReached` = `maxAiTokens != null && aiTokensUsed >= maxAiTokens`
  Both are exposed through the context value alongside the existing seat-limit fields.
- **`SubscriptionPage.jsx`**: the current-subscription `detail-card`/`horizontal-detail-list`
  gains a token-usage row (`aiTokensUsed` / `maxAiTokens`, showing "Unlimited" when
  `maxAiTokens` is null), and a new `warning-banner-card` (reusing the existing seat-limit-
  reached banner styling) appears when `isAiTokenLimitReached` is true.
- **`AiTreatmentPlanModal.jsx`**:
  - Reads `isAiTokenLimitReached` from `useAppState()`. When true, the "Generate" button is
    disabled (added to its existing `disabled` condition) and a dedicated inline warning is
    shown (new translation key), proactively — mirroring how `canCreateActiveUser` disables the
    "create user" button elsewhere in the app.
  - In `handleGenerate`'s catch block, additionally checks `error?.errors?.ai_tokens` (the
    `ApiError.errors` object, populated from the backend's `422` `errors` payload — see
    `lib/api.js:41-58`) to show the same localized warning instead of the raw backend message
    string. This is a defensive fallback for the race where the cap is crossed by another
    request between the page loading and this one being submitted.
- New translation keys added to `en.json`/`ar.json`/`tr.json` for: the subscription-page usage
  row label, the subscription-page limit-reached banner (title + body), and the treatment-plan
  modal's inline limit-reached warning.

## 9. Error handling

- **No active subscription** → `422` with `errors.ai_tokens`, defensive only (login is already
  gated elsewhere).
- **Limit reached** → `422` with `errors.ai_tokens`; the frontend primarily prevents this by
  disabling the button proactively (§8), with the `422` handler as a defensive fallback.
- **OpenAI response missing/malformed `usage`** → token counts default to `0`; the
  treatment-plan preview itself still succeeds and is returned to the doctor. No error surfaced
  to the user for this case.

## 10. Testing

- **Backend**: extend the existing `Http::fake()` convention in
  `tests/Feature/AiTreatmentPlan/PreviewAiTreatmentPlanTest.php` to include a `usage` block in
  the mocked Chat Completions response, and assert that a successful preview increments the
  subscription's `ai_tokens_used` and writes one `ai_usage_logs` row with the right counts. A
  new test asserts `preview()` returns `422` (and makes no OpenAI HTTP calls at all, verified via
  `Http::assertNothingSent()` or equivalent) when the company's subscription is already at or
  over `max_ai_tokens`. A unit test on `AiTokenUsageService` covers: under the cap (passes),
  at/over the cap (throws), and `max_ai_tokens = null` (always passes, unlimited).
- **Frontend**: no existing automated test suite for this area (matches project convention) —
  verified manually: subscription page shows correct usage/limit banner, treatment-plan modal
  disables generation and shows the warning once the (test) company's subscription is put over
  its cap via the admin panel.

## 11. Implementation phasing

Following the same convention as the prior AI treatment-plan design: one spec, two separate
implementation plans/sessions, one per repository.

1. **Backend first** (`Dental_Backend`): the two migrations, `AiUsageLog` model, the
   `AiTokenUsageService`, the `OpenAiClient`/`AiTreatmentPlanService`/
   `AiTreatmentPlanController` changes, the `SubscriptionResource` fields, and the admin panel
   form/list changes. Fully testable via `Http::fake()` without the frontend existing yet.
2. **Frontend second** (`Dental_FrontEnd`): the `AppStateApiContext.jsx` mapping/computed
   values, the `SubscriptionPage.jsx` usage row + banner, the `AiTreatmentPlanModal.jsx`
   proactive block + defensive catch, and translation keys — built against the real backend
   endpoints from step 1.

## 12. Open items for the implementation plan

- Exact migration filenames/ordering (to be picked at implementation time following the
  existing `database/migrations` naming convention).
- Exact wording of the new translation strings across `en`/`ar`/`tr` — a copywriting detail,
  not an architectural one.
- Whether `ai_usage_logs.action` should be a plain string or a backed PHP enum (like
  `AppointmentType`/`AttendanceStatus` elsewhere) — minor, decided during implementation; a
  plain string is sufficient today since there is exactly one action value in use.
