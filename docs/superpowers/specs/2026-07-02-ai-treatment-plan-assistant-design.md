# AI Treatment Plan Assistant — Design

Date: 2026-07-02
Repos affected: `Dental_Backend` (Laravel API) and `Dental_FrontEnd` (Zoho CRM plugin, React/Vite)

## 1. Problem & Goal

A doctor viewing a patient's file today has to manually: figure out how many follow-up
sessions a diagnosis requires, create each `Appointment`, and (per session, once checked in)
fill in the Odontogram v2 chart and a description of what will be done.

This feature adds an "AI Treatment Assistant" button on the client's page. The doctor types
(or optionally dictates) a short case description — e.g. "tooth 13, pulp necrosis" — and OpenAI
proposes a sequence of future sessions: how many, how far apart, and for each session which
teeth/conditions to mark on the Odontogram v2 chart plus a short explanation of what will
happen in that session. The doctor reviews and edits the proposal, then confirms it, which
creates the real `Appointment` records.

## 2. Scope boundaries (explicit, to prevent scope creep)

- **In scope**: per-session planning (appointments + per-session Odontogram v2 data + a
  written explanation per session). Both repos change.
- **Out of scope**:
  - The client's overall `TreatmentRecord` (the itemized pricing ledger) is untouched. That
    remains the existing manual flow.
  - No audio/TTS output. The AI's explanation is written text only (confirmed with user —
    the original idea of a spoken explanation was dropped in favor of immediate DB actions
    with written per-session text).
  - No AI-generated (e.g. DALL·E) images. The "image" is a PNG snapshot of the *real*
    Odontogram v2 widget rendering the AI's structured tooth data — same rendering the app
    already uses for treatment records and PDF export.
  - Only available for companies on Odontogram v2 (`isCompanyVersion2`). Odontogram v1
    companies do not see the AI button.
  - No per-company subscription gating for now — one platform-wide OpenAI key.

## 3. Why the plan lives on `Appointment`, not a pre-created `Visit`

`visits.attendance_status` is a required enum with only three values: `attended`, `no_show`,
`walk_in`. Every existing `Visit` represents something that already happened — there is no
"planned, hasn't happened yet" state, and check-in (`ClientVisitController::checkIn()`)
currently *creates* the Visit at the moment of check-in.

Rather than adding a `planned` status (which would ripple into every place that lists/filters
visits, and complicate `checkIn()`/`noShow()`), the AI's per-session plan is stored on the
`Appointment` — which already represents a future scheduled slot — and is only copied into a
real `Visit` when the doctor actually checks that appointment in. This requires no change to
the meaning of "Visit" anywhere else in the app.

## 4. Data model changes (Backend)

New nullable columns, added via migration:

**`appointments`**
- `planned_summary` (longText) — JSON envelope, *identical shape* to what the frontend already
  writes to `visits.summary` today (the `__visit_odontogram__` marker format produced by
  `serializeVisitOdontogramSnapshot()` in `visitOdontogram.js`), containing `companyVersion: 2`
  and `odontogramV2Status`. Reusing this exact shape means `parseVisitOdontogramSnapshot()` and
  `OdontogramV2Widget` need zero changes to render it, whether it's sitting on an Appointment
  (pre-visit) or a Visit (post-check-in).
- `planned_notes` (longText) — the AI-written explanation of that specific session (mirrors the
  existing `notes` column semantics on `Visit`, kept separate from the doctor's own manual
  `appointments.notes` field so the two don't collide).
- `planned_image_path` (string) — storage path (public disk) of the rendered Odontogram v2 PNG
  for this session.

**`visits`**
- `odontogram_image_path` (string, nullable) — new column so the rendered image persists once
  the appointment becomes a real visit. (`visits.summary`/`visits.notes` already exist and are
  reused as-is.)

**`ClientVisitController::checkIn()`** change: when building the new Visit, default
`summary`/`notes`/`odontogram_image_path` from the appointment's `planned_summary` /
`planned_notes` / `planned_image_path` whenever the doctor doesn't supply their own values in
the check-in request. The doctor can always override at check-in time; nothing is locked in
by the AI.

## 5. Odontogram vocabulary constraint

The vendored Odontogram v2 engine (`src/vendor/odontogram-v2/odontogram.ts`) supports a large
set of tooth states (crown materials, bridge units, endo variants, filling materials, indicator
flags, etc.). Asking GPT to freely emit any string for these fields risks invalid/unsupported
values reaching the widget.

Instead, the AI's structured output is constrained (via OpenAI JSON-schema strict mode, enum
values) to exactly the vocabulary the app already prices in `odontogramV2.js`'s
`DEFAULT_PRICE_BY_KIND` map: `toothSelection` (implant, tooth-crownprep, tooth-under-gum,
no-tooth-after-extraction), `crownMaterial` (emax, zircon, metal, temporary, telescope, radix,
broken), `bridgeUnit` (zircon, metal, temporary, removable, bar, bar-prosthesis), `endo`
(endo-medical-filling, endo-filling, endo-filling-incomplete, endo-glass-pin, endo-metal-pin,
endo-resection, parapulpal-pin), `fillingMaterial` (amalgam, composite, gic, temporary), `mods`
(inflammation, parodontal, mobility), and the indicator flags. This list is mirrored as PHP
constants in the backend (a comment there notes it must be kept in sync with
`odontogramV2.js`'s `DEFAULT_PRICE_BY_KIND` if that list ever changes). If the doctor's
description implies a condition outside this vocabulary, the model is instructed to skip the
odontogram change for that tooth and say so in the session's explanation text rather than
inventing an unsupported value.

## 6. Backend flow

Two new endpoints under the existing `auth:sanctum` group, following the existing
`Client{X}Controller` + `Service` pattern:

### `POST /clients/{client}/ai-treatment-plan` (preview — nothing persisted)

Request: `{ description: string, audio?: file }`. If `audio` is present, it's transcribed via
OpenAI's Whisper endpoint first and the resulting text is used as `description` (both text and
optional voice input are supported, per confirmed requirement).

Processing (new `AiTreatmentPlanService`):
1. Call OpenAI Chat Completions with `response_format: json_schema` (strict), via Laravel's
   `Http` facade — no new composer dependency, since this is a single call site. The schema
   requires: a short `diagnosis_summary`, and a `sessions` array (max 8 — see §7) where each
   session has `day_offset` (days from the previous session), `duration_minutes` (one of
   30/60/90, matching the existing `StoreAppointmentRequest` constraint), `teeth[]`
   (tooth number + condition/treatment fields restricted to the vocabulary from §5), and
   `session_description` (free text, same language as the doctor's input).
2. For each session, resolve a concrete date/time: starting from `previous_session_date +
   day_offset` (first session starts from today), use `DoctorAvailabilityService` for the
   *currently authenticated doctor* to find the nearest available start time of the requested
   duration, rolling forward day-by-day (skipping non-working days) up to a 14-day search cap
   if the target day is fully booked.
3. Return the full draft (session dates/times + per-session `odontogramV2Status` JSON +
   explanation text) to the frontend. Nothing is written to the database.

### `POST /clients/{client}/ai-treatment-plan/confirm`

Request: multipart — `sessions` (JSON, the draft above, possibly edited by the doctor: dates,
odontogram entries, or explanation text) + one image file per session (the PNG captured
client-side, see §7).

Processing, inside one DB transaction:
1. Re-validate each session's date/time against `AppointmentConflictService`
   (`assertWithinSchedule` + `assertNoConflict`) — authoritative check, since time has passed
   since the preview call and a slot may have been taken.
2. If any session fails validation, the whole request fails (no partial writes) with a
   validation error identifying which session and why.
3. Otherwise, create one `Appointment` per session (`type: booked`, `status: scheduled`,
   `doctor_id` = current doctor, `client_id` = the client) with `planned_summary`,
   `planned_notes`, and `planned_image_path` (after storing the uploaded PNG on the `public`
   disk) filled in.
4. Return the created appointments (same `AppointmentResource` shape used elsewhere).

## 7. Frontend flow

New "AI Treatment Assistant" entry point on `ClientDetailsPage`, shown only when
`isCompanyVersion2`. Opens a panel:

1. Doctor types a description (a mic button is also available; recording is sent to the
   preview endpoint as the `audio` field for Whisper transcription — both input modes are
   supported, per confirmed requirement).
2. Frontend calls the preview endpoint and renders the draft as an editable list of proposed
   sessions: date/time (adjustable, re-validated against `DoctorAvailabilityService` client-side
   the same way the existing appointment form does), an `OdontogramV2Widget` per session
   pre-populated with the AI's `odontogramV2Status` (editable using the existing widget
   interactions — no new odontogram UI needed), and an editable explanation text field.
3. On "Confirm": for each session, the already-rendered `OdontogramV2Widget` DOM node is
   captured via `toPng()` from `html-to-image` — the same call already used in
   `ClientDetailsPage.jsx`'s `handleExportPdf()` for the Odontogram v2 PDF preview — producing
   the PNG that gets uploaded.
4. The confirm endpoint is called with the (possibly edited) session data and the captured
   images. On success, the panel closes and the newly created appointments appear in the
   existing appointments list/calendar.

No new npm dependency is required (`html-to-image` is already installed).

## 8. Guardrails & error handling

- **Session cap**: the preview call rejects/fails if the model proposes more than 8 sessions,
  asking the doctor to split the case into a follow-up request. Prevents runaway plans from a
  single ambiguous description.
- **Vocabulary constraint**: see §5 — invalid enum values are structurally impossible via the
  JSON schema; conditions outside the supported vocabulary are described in text instead.
- **Scheduling race**: preview availability is informational; confirm re-validates
  authoritatively inside the transaction (§6). A conflict at confirm time fails cleanly,
  session-by-session, with nothing partially saved.
- **OpenAI/Whisper failures** (timeout, malformed structured output, content policy): the
  preview endpoint returns a normal validation-style error. Because all persistence happens
  only in `confirm`, a failed or partial AI response never touches the database.
- **No client-side OpenAI key**: `OPENAI_API_KEY` lives server-side only (`.env`); the Zoho
  iframe frontend never calls OpenAI directly.

## 9. Testing

- **Backend**: feature tests for both endpoints with the OpenAI/Whisper HTTP calls faked via
  `Http::fake()` (no real API calls in CI): valid plan → appointments created with correct
  `planned_*` fields; session-cap rejection; unsupported-vocabulary handling (condition dropped,
  explained in text instead); confirm-time conflict race (one session's slot taken between
  preview and confirm → whole confirm fails, nothing persisted). A unit test on `checkIn()`
  verifying `planned_summary`/`planned_notes`/`planned_image_path` correctly default onto the
  new Visit when the doctor supplies no override, and that explicit doctor input at check-in
  wins over the AI's planned values.
- **Frontend**: no new automated test suite (matches the project's existing convention of no
  frontend tests) — verified manually: golden path (type description → review draft → edit a
  session's date and odontogram → confirm → appointments appear), and the voice-dictation input
  path.

## 10. Implementation phasing

Per your preference, this single design covers both repos, but implementation happens as two
separate plans/sessions, one per repository:

1. **Backend first** (`Dental_Backend`): migrations, `AiTreatmentPlanService`, the two
   endpoints, the `checkIn()` change, and the PHP-mirrored odontogram vocabulary constants.
   The preview/confirm endpoints can be built and tested (via `Http::fake()`) without the
   frontend existing yet.
2. **Frontend second** (`Dental_FrontEnd`): the AI Treatment Assistant panel, wiring to the two
   new endpoints, and the `html-to-image` capture-and-upload step — built against the real
   backend endpoints from step 1.

## 11. Open items for the implementation plan

- Exact OpenAI model choice for the Chat Completions call (a capable, cost-reasonable model
  with reliable structured-output support) and for Whisper transcription — to be pinned down
  during implementation, not a design-level decision.
- Migration naming/ordering for the new columns.
- Whether the "AI Treatment Assistant" panel is a modal or an inline section on
  `ClientDetailsPage` — a frontend layout detail, not architecturally significant, to be
  resolved during implementation with a quick visual check against the existing page.
