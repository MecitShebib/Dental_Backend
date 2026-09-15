# Nutrition (Dietavaria) Specialty Expansion — Design

Date: 2026-09-15
Repos affected: `Dental_Backend` (Laravel API) and `Dental_FrontEnd` (React/Vite) — both get real
structural changes under this spec.

## 1. Problem & Goal

The Nutrition specialty ("Dietavaria") already exists as a real, deployed v1 specialty
(`app/Specialties/Nutrition/`, `specialty.key = 'nutrition'`, sort_order 6) — it reuses the same
generic patient-management skeleton every specialty gets (client details tabs, care plans,
lab results, AI treatment assistant), copied from dental's shape with only the odontogram
("Diagnosis") tab already dropped.

Two problems with that generic skeleton for this specialty specifically:

1. It still exposes sections that make sense for a doctor treating teeth/bodies clinically but
   have no meaning for a dietitian managing a nutrition case (X-ray imagery, dental lab case
   tracking).
2. It has none of what a dietitian actually needs day-to-day: a structured nutrition profile
   (height, dietary restrictions, chronic conditions relevant to diet, goals), a place to record
   body-composition measurements (weight, body fat %, muscle mass, etc.) over time, an AI
   assistant that automatically sees that data instead of requiring it to be typed into the chat
   by hand, and a periodic-review loop where each new measurement is compared against the
   previous one so the AI can judge progress and refresh the plan for the next cycle.

Goal: trim the generic skeleton down to what a nutrition case actually needs, then build the
missing nutrition-specific data model, capture UI, and AI-context pipeline on top of it — reusing
existing platform services (`CarePlanService`/`MilestoneCarePlanService`, `AiConversationService`,
the `clinical_data` JSON-on-milestone convention) rather than inventing parallel mechanisms.

This spec covers five sub-projects, built and delivered as five separate implementation passes
(each gets its own plan under `writing-plans`, in this order), because they are only loosely
coupled and each is independently shippable:

1. Patient-management trim (remove what a dietitian doesn't need)
2. Nutrition profile fields (static per-client data)
3. Body-composition measurement capture (device/manual data over time)
4. AI body-data awareness + nutrition quick-reply presets
5. Periodic follow-up comparison and plan-refresh loop

## 2. Sub-project 1 — Patient-management trim

**In scope:**

- `NutritionClientDetailsPage.jsx`: remove the "Röntgen" (X-ray) tab entirely — its tab-bar entry,
  the `XrayImagePicker` render block, and the now-unused import.
- `Sidebar.jsx`: hide the **X-Ray Images**, **CBCT Scans (DICOM)**, and **Lab** (dental lab
  case tracking — crowns/bridges/veneers, `/lab/cases`) nav items when
  `activeSpecialtyKey === "nutrition"`. These three are dental-imaging/dental-lab concepts with
  no nutrition equivalent.

**Explicitly out of scope:**

- No backend route or controller is removed — `xray-images`, `dicom-studies`, and `/lab/cases`
  stay fully functional for dental and are still reachable directly by URL; only the nutrition
  sidebar's *links* to them are hidden.
- No change to the other four non-dental specialties' sidebars (Gynevaria/Medivaria/Orthovaria/
  Estevaria) even though they may have the same unguarded nav items — confirmed out of scope by
  the user, scoped to nutrition only for this pass.
- **Lab Results** (blood work / test results, `ClientLabResultsPanel`) and **Prescriptions**
  stay exactly as they are — both already make sense for a dietitian (blood panels feed directly
  into nutrition decisions; prescriptions were confirmed to stay as-is, e.g. for supplement
  recommendations, by explicit user decision during brainstorming).

## 3. Sub-project 2 — Nutrition profile fields

A dietitian needs two different *kinds* of client data: things that rarely change (profile) and
things that are re-measured every visit (§4). This sub-project is the profile half only.

**New table `nutrition_client_profiles`** (one row per `client_id`, `HasUuid`, standard
`company_id`/timestamps):

- `height_cm`
- `dietary_type` (enum-like string: omnivore / vegetarian / vegan / halal / kosher / other)
- `allergies` (JSON array of free-text tags)
- `chronic_conditions` (JSON array of free-text tags — e.g. diabetes, hypertension, thyroid
  disorder, PCOS, kidney disease; free-text rather than a fixed enum so it isn't a bottleneck)
- `medications_affecting_diet` (text)
- `smoking_status` / `alcohol_status` (simple enums: none / occasional / regular)
- `activity_level` (enum: sedentary / light / moderate / active / very_active)
- `goal` (enum: weight_loss / weight_gain / maintenance / muscle_gain / medical_diet / other)
- `target_weight_kg` (nullable)
- `notes` (text, free-form)

**Frontend:** fields added to `NutritionClientEditPage.jsx`; displayed as a new block inside the
existing "Hasta Verisi" (`clientData`) tab of `NutritionClientDetailsPage.jsx`, alongside the
existing generic fields (name, phone, city, age, address, medical notes).

**Out of scope:** weight itself is *not* a profile field — it belongs in the repeated-measurement
table in §4, since it changes visit to visit. `target_weight_kg` is the one weight-shaped value
that legitimately belongs on the profile (it's a goal, not a measurement).

## 4. Sub-project 3 — Body-composition measurement capture

**New table `nutrition_body_metrics`**: `client_id`, `recorded_at` (date), `source`
(`manual` | `device_import`), `weight_kg`, `bmi` (computed server-side from weight + the
profile's `height_cm` at save time), `body_fat_percent`, `muscle_mass_kg`,
`visceral_fat_rating`, `water_percent`, `bone_mass_kg`, `basal_metabolic_rate`, `waist_cm`,
`hip_cm`, `notes`, `report_file_path` (nullable — an uploaded device report image/PDF, stored the
same way X-ray/consent files are today: private disk, per the KVKK migration), `raw_payload`
(nullable JSON, reserved for a future structured device import), `visit_id` / `appointment_id`
(nullable FKs — ties a measurement to the visit it was taken at), `recorded_by` (user id).

**Capture UI:** a manual-entry form matching typical body-composition-analyzer output fields
(InBody/Tanita-style), reachable from a new panel on the Client Details page, plus an optional
file upload for the device's printed/exported report. Measurements render as a simple
chronological list/trend (weight and body-fat-% sparkline) on that panel.

**Decision — device integration approach:** real wireless/API integration with a specific device
brand (Bluetooth pairing, or a vendor cloud API like Withings/Withings Health or an InBody export
API) is **not** built in this pass. It requires picking a specific device model/vendor first
(protocols, and in most cases a paid vendor partnership/API key, differ completely by brand), and
browser-side Bluetooth (Web Bluetooth API) doesn't work on iOS Safari at all — a real constraint
for a clinic tablet/iPad workflow. `source` and `raw_payload` are deliberately included now so a
real device-import path can be added later as a new `source` value without a schema change; the
manual-entry form is what actually ships. If a specific device is later chosen, that becomes its
own follow-up spec.

## 5. Sub-project 4 — AI body-data awareness + nutrition quick-reply presets

**Automatic context injection:** every AI message sent from a nutrition conversation is
augmented server-side (in `AiConversationService`, following the existing pattern where
`AiTreatmentPlanService` already assembles client data into prompts) with a structured summary
built from the client's `nutrition_client_profiles` row plus their `nutrition_body_metrics`
history (most recent measurement in full, prior measurements as a compact trend line) — the
doctor never has to type this in by hand. If the latest measurement has an uploaded report file,
it's attached to the OpenAI call as a base64 data URI, the same mechanism already fixed for
X-ray images in the AI chat (`project_ai_conversation_xray_url_fix` memory) — signed private-disk
URLs don't work with OpenAI's fetcher, base64 does.

**Quick-reply presets:** the existing `AiTreatmentPlanModal.jsx` quick-reply mechanism
(`ai-chat-quick-replies`, currently generic/client-side only) gets a nutrition-specific preset
set — e.g. "Yeni Değerlendirme Başlat" (start assessment), "Aylık Takip Planı Oluştur" (monthly
follow-up plan), "Diyet Planı Oluştur" (diet plan). Clicking one sends a message whose context
(via the injection above) already carries the full profile + measurement history — no backend
"preset message" concept is needed beyond specialty-conditional button labels/intents on the
frontend, since the actual context-attachment work is the injection mechanism itself.

## 6. Sub-project 5 — Periodic follow-up comparison and plan-refresh loop

Builds on `NutritionCarePlanService` (already extends `MilestoneCarePlanService` for the
"consultation + N evenly-spaced follow-up sessions" shape).

- When an AI-generated plan is confirmed (`confirmPlan`), the plan's `clinical_data` (same JSON
  convention already used for `program_session_type`/`session_count`/`session_number`) gains a
  `baseline_metric_id` pointing at the `nutrition_body_metrics` row that was current at
  confirmation time, plus the plan's start date — this is the "cycle" record; no new table needed,
  it rides on the existing milestone/clinical_data mechanism.
- When a new `nutrition_body_metrics` row is recorded for a client with an open cycle (a
  confirmed plan whose milestones haven't all completed yet), the system computes deltas
  (weight/body-fat/etc. change) against the cycle's baseline and the elapsed days, and folds that
  comparison into the same AI-context injection from §5 ("29 days since baseline; weight
  77kg → 74kg, body fat 26% → 24%") — the AI decides whether to continue, adjust, or close the
  cycle and start a new one via the same quick-reply flow, rather than the backend forcing a new
  plan automatically.
- Scheduling and charge creation for the next cycle's appointments reuse the existing
  `CarePlanService`/`MilestoneCarePlanService` confirm flow unchanged — this sub-project only adds
  the comparison data that feeds the AI's decision, it does not add new scheduling logic.

## 7. Testing

Each sub-project's implementation plan includes Feature tests following this project's existing
per-specialty test layout (`tests/Feature/Nutrition/*`, mirroring the other four non-dental
specialties): sub-project 2/3 get model + endpoint tests for the new tables; sub-project 4/5 get
tests asserting the AI context payload contains the expected profile/measurement/comparison data
(mocking `OpenAiClient` the same way `AiConversationTest`/`AiTreatmentPlan` tests already do)
rather than asserting on real OpenAI output.

## 8. Sequencing

Sub-projects are implemented and shipped in the order 1 → 2 → 3 → 4 → 5, each as its own
`writing-plans` plan and its own review checkpoint, since 4 and 5 depend on the data model built
in 2 and 3.
