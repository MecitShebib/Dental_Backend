# CBCT/DICOM Viewer — Design

Date: 2026-09-05
Repos affected: `Dental_Backend` (Laravel API, DICOM storage/metadata) and `Dental_FrontEnd`
(React/Vite, Cornerstone3D-based viewer UI).

## 1. Problem & Goal

The user saw a competitor's marketing image listing five imaging-product features (2D/3D viewing,
MPR viewing, Measurement & Analysis, KOS Integration, WADO viewing) and asked whether this project
supports them. It does not — the existing "X-Ray Images" feature (`XrayImage`,
`XrayImageController`) only accepts flat JPG/PNG/WEBP photographs (`app/Http/Requests/XrayImage/
StoreXrayImageRequest.php`: `'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', ...]`); there is no
DICOM parsing, no multi-slice volume, no 3D/MPR reconstruction, and no measurement tooling
anywhere in the codebase (confirmed by grep — the few "MPR"/"PACS" string matches in the repo are
unrelated substring hits).

This spec covers building **2D/3D viewing, MPR viewing, and Measurement & Analysis** as a new,
separate feature. **KOS Integration and WADO** (i.e. connecting live to an external PACS/national
health system to pull studies automatically) are explicitly deferred — user's own framing:
"PACS entegrasyonu ilerde yapacağız" (we'll do PACS integration later). For now, studies are
uploaded manually (a doctor/staff member exports a study from a CBCT/CT machine and uploads it
here) — no live PACS connection exists or is being built in this phase.

## 2. Scope boundaries

- **In scope**: uploading a real multi-slice CT/CBCT DICOM study (as a `.zip` or as multiple
  loose `.dcm` files), storing it, browsing studies in a company-wide gallery, linking a study to
  a `Client`, and an in-page (not popup) viewer with four synchronized panes (Axial/Sagittal/
  Coronal/3D), plus three measurement tool families: distance/angle, Hounsfield-unit density
  probing, and implant/nerve-canal path marking. All five specialties (Dentavaria, Gynevaria,
  Medivaria, Orthovaria, Estevaria) — confirmed with user, not dental-only.
- **Out of scope (this spec)**:
  - Any live PACS/WADO/KOS connection. No external system talks to this feature yet; every study
    arrives via manual upload.
  - Editing or re-slicing the underlying DICOM data (windowing/contrast adjustment is a *view*
    setting, not a data mutation — nothing here writes back into the stored DICOM files).
  - Automated analysis (AI-assisted density/pathology detection). "Analysis" in this spec means
    manual measurement tools a doctor operates, not an automated read.
  - Converting the *existing* `XrayImage` (flat JPG/PNG) feature into DICOM, or merging the two.
    They stay separate, parallel features — a clinic may have both a 2D X-ray photo and a CBCT
    study on file for the same client, filed independently, matching how the user explicitly
    chose "yeni bir özellik, X-ray'e ek" over "extend the existing X-ray feature."
- **Explicitly large, phased at implementation time** (see §7): the implant/nerve-canal marking
  tool is a materially bigger and riskier piece of work than the other two measurement types. The
  data model and UI are designed to support it from day one, but `writing-plans` should sequence
  it as the last milestone, after viewing + distance/angle + density are shipped and verified.

## 3. Technical approach

**Client-side rendering via Cornerstone3D** (`@cornerstonejs/core`, `@cornerstonejs/tools`,
`@cornerstonejs/dicom-image-loader`), the open-source library the OHIF viewer and most
browser-based DICOM tools are built on. The browser does all DICOM decoding, 2D/3D/MPR
reconstruction, and measurement-tool math (via WebGL/VTK.js under the hood); the backend's job is
reduced to storing files and light metadata extraction.

Two alternatives were considered and rejected:
- **Server-side pre-processing** (convert DICOM to pre-rendered images/volumes on upload) would
  need real medical-imaging tooling (ITK/VTK, realistically a separate Python service) that
  doesn't fit the existing PHP/Laravel stack, needs heavier CPU/memory than this shared host
  reliably provides, and would still have to re-render on every window/level or MPR-slice change —
  killing interactivity.
- **Hybrid** (server-rendered gallery thumbnails, client-rendered full viewer) is a reasonable
  future nicety but adds a server-side rendering dependency for zero functional gain in v1; the
  gallery can show static metadata (modality/date/slice count) without a rendered thumbnail
  initially.

This choice is why the backend section below is deliberately thin: no image-processing pipeline,
no queue-dependent processing step (relevant given `project_shared_hosting_deploy.md` — this host
has no reliable persistent queue worker; a design that *required* one for core functionality would
be fragile here).

## 4. Data model

### New table `dicom_studies`

- `id`, `uuid` (via `HasUuid`)
- `company_id` — FK → `companies` (via `BelongsToCompany`, same tenancy pattern as `XrayImage`)
- `client_id` — FK → `clients`, nullable (uploaded-but-unlinked studies, same "unlinked" gallery
  filter concept `XrayImageController::index()` already has for X-rays)
- `uploaded_by` — FK → `users`
- `modality` — string (`CT`, `CBCT`) — read from the DICOM `Modality` tag where present, else set
  from an explicit upload-form field as a fallback
- `study_date` — date, read from the DICOM `StudyDate` tag
- `description` — string, nullable (DICOM `StudyDescription`, editable after upload)
- `slice_count` — integer, denormalized count across all series, for gallery display
- `status` — enum-like string: `processing` (zip extraction / metadata scan in flight),
  `ready`, `failed`
- timestamps, soft-deletes (`SoftDeletes` — matches the just-built `Company`/`Subscription`/`User`
  cascade-delete precedent; deleting a client or company should not silently hard-delete imaging
  history)

### New table `dicom_series`

- `id`, `uuid`
- `dicom_study_id` — FK → `dicom_studies`, cascade on delete
- `series_uid` — string (DICOM `SeriesInstanceUID`, for de-duplication on re-upload)
- `rows`, `columns` — integer (pixel dimensions per slice)
- `slice_count` — integer
- `pixel_spacing_x`, `pixel_spacing_y` — decimal (mm per pixel, from `PixelSpacing`)
- `slice_thickness` — decimal (mm, from `SliceThickness`)
- `orientation` — string/JSON (`ImageOrientationPatient` — needed to correctly build MPR/volume
  geometry; stored as-read, interpreted client-side by Cornerstone3D)
- `storage_path` — string (directory under the `public` disk holding this series' `.dcm` files)
- timestamps

### New table `dicom_measurements`

- `id`, `uuid`
- `dicom_study_id` — FK → `dicom_studies`, cascade on delete
- `type` — string: `distance`, `angle`, `density_probe`, `nerve_path`
- `points` — JSON (array of `{x, y, z}` or per-slice image-coordinate points; shape depends on
  `type` — a `distance` has 2 points, `angle` has 3, `density_probe` has 1, `nerve_path` has an
  ordered polyline of N points)
- `value` — decimal, nullable (the computed mm/degree/HU result — computed client-side by
  Cornerstone3D's tools and sent along, not recomputed server-side)
- `unit` — string (`mm`, `deg`, `HU`)
- `notes` — string, nullable
- `created_by` — FK → `users`
- timestamps

### Model relations

- `Company::dicomStudies()` — `hasMany`
- `Client::dicomStudies()` — `hasMany`
- `DicomStudy::series()` — `hasMany(DicomSeries::class)`, `::measurements()` — `hasMany
  (DicomMeasurement::class)`, `::client()` — `belongsTo`, `::uploader()` — `belongsTo(User::class,
  'uploaded_by')`
- `DicomSeries::study()` — `belongsTo`

## 5. Storage

Raw `.dcm` files are written to the `public` disk (the same disk whose `root` this project already
points at `public_path('storage')` instead of the conventional symlinked path, specifically to work
around this host disabling `symlink()`/`exec()` — see `project_shared_hosting_deploy.md`), under
`storage/dicom-studies/{study_uuid}/{series_uid}/{instance_number}.dcm`. No new storage-layer work
is needed; this reuses the existing, already-fixed `public` disk config as-is.

## 6. Upload flow

New endpoint `POST /api/dicom-studies`, accepting **either** a single `.zip` file **or** an array
of loose `.dcm` files (both supported per user's explicit choice — real-world CBCT exports commonly
arrive as a zip of a whole study folder, but some workflows export loose files).

Processing, synchronous within the request (no queue dependency, consistent with §3's reasoning):
1. If a `.zip` was sent, extract it to a temp directory; collect all files that parse as valid
   DICOM (a lightweight PHP DICOM tag reader — e.g. a small Composer package that only reads the
   header/tag dictionary, not pixel data — distinguishes real `.dcm` files from stray non-DICOM
   files a zip export might include).
2. Group files by `SeriesInstanceUID`; for each group, create one `dicom_series` row (reading
   `rows`/`columns`/`pixel_spacing`/`slice_thickness`/`orientation` from the first instance) and
   move its files into that series' storage path.
3. Create one `dicom_studies` row (modality/date/description from the first file's tags,
   `slice_count` = total across all series, `status = ready`; `client_id` from the request if the
   uploader already knows which patient this is for, else left `null` — unlinked, same as X-ray).
4. On any parse failure, `status = failed` with the error surfaced in the gallery row (no silent
   drop) rather than a generic 500.

## 7. Frontend

### 7.1 Navigation (dual access, mirroring the just-built X-ray pattern)

- New top-level nav page **"CBCT/3D Taramalar"** (company-wide gallery, alongside "X-Ray Images"):
  upload button (accepts `.zip` or multi-`.dcm` selection), grid of study cards (modality, date,
  slice count, linked-client name or "unlinked"), an "unlinked only" filter, and a link/patient
  picker to attach a study to a client after the fact — all directly mirroring
  `XrayGalleryPage.jsx` / `XrayImagePicker.jsx`'s existing UX so this doesn't introduce a new
  interaction pattern to learn.
- New **"CBCT" tab** on `ClientDetailsPage.jsx` (and the four specialty equivalents), appended
  after the existing tabs — same placement/mechanism as the "AI"/"X-Ray" tabs added this session:
  lists this client's own studies, clicking one opens the viewer **inline in the page, not a
  popup** (per explicit instruction, consistent with every other panel converted this session).

### 7.2 Viewer layout (user-approved mockup option "A")

Four synchronized panes in a 2×2 grid: Axial (top-left), Sagittal (top-right), Coronal
(bottom-left), 3D volume render (bottom-right) — the standard radiology MPR layout. Clicking a
point in any 2D pane cross-hairs the corresponding location in the other two 2D panes
(Cornerstone3D's built-in synchronizer handles this).

A persistent toolbar above the panes holds: window/level (drag), the three measurement tools
below, and a slice/scroll indicator.

### 7.3 Measurement tools

All three confirmed in scope; `dicom_measurements` (§4) already models all of them so no schema
rework is needed between phases:
- **Distance** (2-point, mm) and **Angle** (3-point, degrees) — Cornerstone3D ships these as
  built-in annotation tools; thin wrapper work only.
- **Density probe** (1-point Hounsfield-unit readout) — also a built-in Cornerstone3D tool
  (`Probe`), reading the raw voxel value at a point.
- **Implant/nerve-canal path** — an ordered multi-point polyline marking a safe distance along a
  nerve canal. Materially more custom UI work (no off-the-shelf Cornerstone3D tool matches this
  exactly; likely built on its `Spline`/custom-annotation primitives). Flagged in §2 as the
  last-sequenced milestone.

Every measurement a user creates is persisted (`POST` to a `dicom-studies/{study}/measurements`
endpoint) and redrawn on next open — measurements are not session-only.

### 7.4 Access control

Same rule as X-ray/AI: uploading and linking a study to a client is available to any staff member;
opening the interactive viewer and creating measurements is restricted to `is_doctor` or System
Manager (`authUser?.isDoctor || isSystemManager`), matching the existing convention in
`PatientsPage.jsx` / `ClientDetailsPage.jsx` for clinical tools.

## 8. Testing

- Backend: Feature tests for upload (zip and loose-file paths), series/study grouping by
  `SeriesInstanceUID`, tenant isolation (a study from company A's client_id can't be fetched by
  company B), and the failed-parse path setting `status = failed` rather than 500ing. Unit tests
  for whatever DICOM-tag-reading helper is introduced.
- Frontend: given Cornerstone3D rendering isn't meaningfully unit-testable, verification here is
  manual browser testing per this project's established pattern (`run` skill) rather than
  automated — matching how the 3D Odontogram panel (also WebGL-based) was verified.

## 9. Risks & open questions

- **PHP DICOM tag-reading.** PHP's DICOM-parsing ecosystem is thin compared to Python's (no
  `pydicom` equivalent with wide adoption). §6 assumes a small Composer package can read just the
  handful of tags this needs (`SeriesInstanceUID`, `Modality`, `StudyDate`, `Rows`/`Columns`,
  `PixelSpacing`, `SliceThickness`, `ImageOrientationPatient`) without decoding pixel data —
  `writing-plans` should confirm a suitable package exists before committing to it, or budget time
  to hand-roll a minimal tag reader (DICOM's header format for these specific, uncompressed tag
  types is not large to parse directly if no library is a good fit).
- **Shared-hosting upload limits.** A CBCT study zip can be 50–300+ MB. This host's PHP
  `upload_max_filesize`/`post_max_size`/`max_execution_time` (set via `.env`/php.ini, see
  `project_shared_hosting_deploy.md` for this host's other PHP-config quirks) may need raising:
  confirm actual current values before implementation, since a study that exceeds them fails
  silently at the web-server layer before Laravel ever sees the request. If the host's ceiling
  can't be raised enough, a chunked/resumable upload becomes a required fallback, not a nicety.
- **Initial client-side load time.** Because rendering is fully client-side (§3), opening a study
  downloads its full slice set to the browser before the 3D/MPR panes can render. Acceptable for
  v1 per the approved approach, but worth setting expectations with the user that a large study
  may take several seconds to open, especially over a slower connection.

## 10. Open items for `writing-plans` to sequence

1. Milestone 1: upload + storage + gallery + client-details tab + 4-pane viewer, no measurement
   tools yet (viewing only).
2. Milestone 2: distance/angle + density probe (both are thin wrappers over built-in Cornerstone3D
   tools — low incremental risk once Milestone 1's viewer is working).
3. Milestone 3: implant/nerve-canal path tool (custom annotation UI — highest risk, sequenced
   last so it doesn't block shipping the rest).
