# AI Treatment Plan Charge — Design

Date: 2026-07-08
Repos affected: `Dental_Backend` (Laravel API) and `Dental_FrontEnd` (React/Vite) — this spec covers the backend half in full and the frontend's contract with it; the frontend's own UI details live in `Dental_FrontEnd`'s `docs/superpowers/specs/2026-07-08-ai-treatment-plan-assistant-polish-design.md`.

## 1. Problem & Goal

Confirming an AI-generated treatment plan (`AiTreatmentPlanService::confirm()`) creates real
appointments for a client, but nothing about that action affects what the client owes.
`ClientFinancialSummaryService::summary()` computes `total_services_amount` solely from
`$client->treatmentRecord->total_services_amount`, which is itself a derived value —
`TreatmentRecordService` recalculates it from the odontogram's per-tooth `unit_price` sum every
time the treatment record is saved (`app/Services/TreatmentRecordService.php:47`). An AI-plan
fee cannot be written into that same field: the next odontogram edit would silently overwrite it.

This feature adds a doctor-entered fee, captured immediately after an AI treatment plan is
confirmed, that adds to (never replaces) the client's total owed amount, independently of the
odontogram-derived figure.

## 2. Scope boundaries

- **In scope**: a new table/model for AI-plan charges, one new endpoint to create a charge, and
  the `ClientFinancialSummaryService` change to include charges in the total-owed calculation.
- **Out of scope**:
  - Editing or deleting a charge once created — no endpoint for it. If a doctor enters the wrong
    amount today, correcting it is a manual DB fix; a proper edit/void flow is a future concern
    if it comes up.
  - Per-appointment/per-session charges — confirmed with user as one lump-sum amount per
    confirmation event, not split across the sessions it was created from.
  - Any change to `TreatmentRecordService` or the odontogram pricing model — that field keeps
    behaving exactly as it does today; this is a fully separate, additive number.
  - Any change to how `Payment`s work — they continue to subtract from the combined total exactly
    as before; only what they're subtracted *from* changes.

## 3. Data model

### New table `ai_treatment_plan_charges`

Modeled directly on the existing `payments` table shape:

- `id`, `uuid` (via `HasUuid`)
- `client_id` — FK → `clients`, cascade on delete
- `amount` — decimal(12,2)
- `description` — text, nullable (frontend prefills this with the AI plan's diagnosis summary,
  but the field itself is a free-text nullable column, not tied to that origin)
- `created_by` — FK → `users`, nullable, null on delete (the doctor who entered it)
- timestamps

New migration, e.g. `database/migrations/2026_07_08_000000_create_ai_treatment_plan_charges_table.php`
(exact timestamp picked at implementation time to sort after existing migrations).

### `AiTreatmentPlanCharge` model

`app/Models/AiTreatmentPlanCharge.php` — `HasFactory`, `HasUuid`; `$fillable` = the columns
above; `belongsTo(Client::class)` and `belongsTo(User::class, 'created_by')`. No `SoftDeletes`
(nothing deletes these rows — see §2 out-of-scope).

### `Client` model

New relation:

```php
public function aiTreatmentPlanCharges(): HasMany
{
    return $this->hasMany(AiTreatmentPlanCharge::class);
}
```

## 4. `ClientFinancialSummaryService` change

```php
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
```

This is the only behavioral change to the service. `total_services_amount` in the API response
becomes "odontogram treatment cost + all AI-plan charges combined" — the frontend does not need
to know the breakdown, it already only reads the combined figure (`ClientFinancialSummaryService`
frontend consumer is `apiSummary?.total_services_amount` in `AppStateApiContext.jsx:1074`).

## 5. New endpoint

`POST /clients/{client}/ai-treatment-plan/charge`

- Guarded the same way as `.../ai-treatment-plan/confirm`: `auth:sanctum` + doctor-only
  (`assertIsDoctor`, same helper already on `AiTreatmentPlanController`).
- New `App\Http\Requests\AiTreatmentPlan\AddAiTreatmentPlanChargeRequest`:
  - `amount` — `required`, `numeric`, `min:0.01`
  - `description` — `nullable`, `string`
- New `AiTreatmentPlanController::addCharge()` method:
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
  (`ClientFinancialSummaryService` injected via `app()` here rather than the constructor, since
  no other controller method needs it — matches the codebase's existing lean-constructor style
  for single-use dependencies; revisit if a second method needs it.)
- Route addition in `routes/api.php`, next to the existing two AI treatment plan routes:
  ```php
  Route::post('clients/{client}/ai-treatment-plan/charge', [AiTreatmentPlanController::class, 'addCharge']);
  ```

No new API Resource — the response body is the same summary shape
`ClientFinancialSummaryService::summary()` already produces, matching what
`GET /clients/{client}` nests today, so the frontend can apply it directly without a new mapper.

## 6. Error handling

- Non-doctor caller → same `422` `errors.doctor` shape as `confirm`/`preview` (`assertIsDoctor`).
- `amount` missing/zero/negative/non-numeric → standard `422` validation error on `errors.amount`.
- Client not found → standard `404` (route model binding), unchanged from every other
  `{client}`-bound route.
- No interaction with `AiTokenUsageService` — this endpoint doesn't call OpenAI, so no token
  gating applies here.

## 7. Testing

- Feature test: doctor confirms a plan is not required as a precondition at the HTTP layer (the
  charge endpoint doesn't check that a plan was actually confirmed — see §8) — test posts a valid
  `amount` directly and asserts a `201`, a new `ai_treatment_plan_charges` row, and that
  `GET /clients/{client}` (or wherever the financial summary is exposed) now reflects the
  increased `total_services_amount`.
- Test: non-doctor user → `422`.
- Test: `amount` of `0` or negative → `422`.
- Test: combined total after both a treatment-record price *and* an AI-plan charge exist —
  asserts they add rather than one overriding the other (this is the behavior this whole feature
  exists to deliver, so it gets its own explicit assertion).
- Test: `remaining_amount` after a payment is added on top of an AI-plan charge — asserts the
  payment deducts from the combined total, not just the odontogram portion.

## 8. Open items for the implementation plan

- Whether to validate that the client has at least one AI-plan-created appointment before
  accepting a charge. Decided against for v1: the endpoint just records a doctor-entered number
  against a client, with no hard link back to which appointments prompted it (see §2 — no
  per-appointment breakdown was wanted). Enforcing a precondition here would need a new way to
  detect "this client has an AI-confirmed plan pending a charge," which doesn't exist and isn't
  worth building for a same-session UI flow the frontend already sequences correctly (fee step
  only shows right after a successful `confirm` call — see the frontend spec).
- Exact migration timestamp/filename — picked at implementation time.
