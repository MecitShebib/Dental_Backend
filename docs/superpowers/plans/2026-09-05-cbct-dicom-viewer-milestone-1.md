# CBCT/DICOM Viewer — Milestone 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a doctor/staff member upload a real multi-slice CT/CBCT DICOM study (zip or loose `.dcm` files), browse studies in a company-wide gallery, link one to a client, and open it as an in-page 4-pane (Axial/Sagittal/Coronal/3D) viewer from that client's "CBCT" tab — no measurement tools yet (that's Milestones 2/3, separate plans per the design spec's own phasing).

**Architecture:** Backend stores raw `.dcm` files on the existing `public` disk and extracts a small set of DICOM header tags (via a new hand-rolled `DicomTagReader` — no mature PHP DICOM library exists, and only ~9 known, simple-VR tags are needed) to populate `dicom_studies`/`dicom_series` rows. All rendering happens client-side via Cornerstone3D; the backend never decodes pixel data.

**Tech Stack:** Laravel (migrations/models/controller, mirroring `XrayImage`'s existing shape exactly), React/Vite (`@cornerstonejs/core`, `@cornerstonejs/tools`, `@cornerstonejs/dicom-image-loader`, `dicomParser`).

**Spec:** `docs/superpowers/specs/2026-09-05-cbct-dicom-viewer-design.md`

---

## Before you start

Read `CLAUDE.md` at the repo root first — it documents the multi-tenancy model (`BelongsToCompany`), the `HasUuid` convention, and this project's `public` disk quirk (points straight at `public_path('storage')`, no symlink, because this host disables `symlink()`/`exec()` — see `app/Models/Concerns/BelongsToCompany.php` and `config/filesystems.php` if you want the full story). Every task below follows the exact same shape as the existing `XrayImage` feature (`app/Models/XrayImage.php`, `app/Http/Controllers/Api/XrayImageController.php`) — read those two files before Task 1 if anything below is unclear; they are the reference implementation this whole feature mirrors.

**Scope reminder:** Do not add measurement tools, the nerve-canal tool, or any `dicom_measurements` table/code in this plan — those are separate, later plans (see spec §10). Task 14 below ends with a *viewing-only* Cornerstone3D viewport; resist the urge to wire in `@cornerstonejs/tools` beyond what's needed to pan/zoom/scroll.

---

### Task 1: `dicom_studies` and `dicom_series` migrations

**Files:**
- Create: `database/migrations/2026_09_05_000000_create_dicom_studies_table.php`
- Create: `database/migrations/2026_09_05_000100_create_dicom_series_table.php`

- [ ] **Step 1: Write the `dicom_studies` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dicom_studies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('modality')->nullable();
            $table->date('study_date')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('slice_count')->default(0);
            $table->string('status')->default('processing');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_studies');
    }
};
```

- [ ] **Step 2: Write the `dicom_series` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dicom_series', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dicom_study_id')->constrained()->cascadeOnDelete();
            $table->string('series_uid')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedInteger('columns')->nullable();
            $table->unsignedInteger('slice_count')->default(0);
            $table->decimal('pixel_spacing_x', 8, 4)->nullable();
            $table->decimal('pixel_spacing_y', 8, 4)->nullable();
            $table->decimal('slice_thickness', 8, 4)->nullable();
            $table->string('orientation')->nullable();
            $table->string('storage_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dicom_series');
    }
};
```

- [ ] **Step 3: Run the migrations against the test database to confirm they're syntactically valid**

Run: `php artisan test --filter=NoTestsWillMatchThis 2>&1 | head -5`
Expected: PHPUnit boots (which runs migrations against the in-memory SQLite test DB per `phpunit.xml`) and prints "No tests executed" rather than a migration error. This is just a syntax/FK sanity check before writing real tests in Task 2.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_05_000000_create_dicom_studies_table.php database/migrations/2026_09_05_000100_create_dicom_series_table.php
git commit -m "feat: add dicom_studies and dicom_series tables"
```

---

### Task 2: `DicomStudy` and `DicomSeries` models

**Files:**
- Create: `app/Models/DicomStudy.php`
- Create: `app/Models/DicomSeries.php`
- Modify: `app/Models/Company.php` (add `dicomStudies()` relation, next to the existing `xrayImages()`-style relations)
- Modify: `app/Models/Client.php` (add `dicomStudies()` relation)
- Test: `tests/Feature/DicomStudyTest.php`

- [ ] **Step 1: Write the failing test for the model relations**

```php
<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DicomSeries;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_study_belongs_to_a_company_and_optionally_a_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-1001',
            'name' => 'Study Patient',
            'phone' => '+15550001111',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Sanctum::actingAs($doctor);

        $study = DicomStudy::create([
            'client_id' => $client->id,
            'uploaded_by' => $doctor->id,
            'modality' => 'CBCT',
            'study_date' => '2026-09-01',
            'description' => 'Full arch scan',
            'slice_count' => 200,
            'status' => 'ready',
        ]);

        $this->assertSame($doctor->company_id, $study->company_id);
        $this->assertTrue($doctor->company->dicomStudies->contains('id', $study->id));
        $this->assertTrue($client->dicomStudies->contains('id', $study->id));
    }

    public function test_a_study_has_many_series(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $study = DicomStudy::create([
            'uploaded_by' => $doctor->id,
            'modality' => 'CT',
            'status' => 'ready',
        ]);

        $series = DicomSeries::create([
            'dicom_study_id' => $study->id,
            'series_uid' => '1.2.3.4.5',
            'rows' => 512,
            'columns' => 512,
            'slice_count' => 150,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3.4.5",
        ]);

        $this->assertTrue($study->series->contains('id', $series->id));
        $this->assertSame($study->id, $series->study->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DicomStudyTest.php`
Expected: FAIL — `Class "App\Models\DicomStudy" not found`

- [ ] **Step 3: Write `app/Models/DicomStudy.php`**

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DicomStudy extends Model
{
    use BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'uploaded_by',
        'modality',
        'study_date',
        'description',
        'slice_count',
        'status',
    ];

    protected $casts = [
        'study_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function series(): HasMany
    {
        return $this->hasMany(DicomSeries::class);
    }
}
```

- [ ] **Step 4: Write `app/Models/DicomSeries.php`**

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DicomSeries extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'dicom_study_id',
        'series_uid',
        'rows',
        'columns',
        'slice_count',
        'pixel_spacing_x',
        'pixel_spacing_y',
        'slice_thickness',
        'orientation',
        'storage_path',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(DicomStudy::class, 'dicom_study_id');
    }
}
```

- [ ] **Step 5: Add the `dicomStudies()` relation to `Company` and `Client`**

In `app/Models/Company.php`, add next to the existing `xrayImages()`-style `hasMany` relations (find that method for the exact insertion spot):

```php
    public function dicomStudies(): HasMany
    {
        return $this->hasMany(DicomStudy::class);
    }
```

In `app/Models/Client.php`, add alongside its other `hasMany` relations:

```php
    public function dicomStudies(): HasMany
    {
        return $this->hasMany(DicomStudy::class);
    }
```

(If `Client.php` doesn't already import `Illuminate\Database\Eloquent\Relations\HasMany`, add the `use` statement.)

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/DicomStudyTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Models/DicomStudy.php app/Models/DicomSeries.php app/Models/Company.php app/Models/Client.php tests/Feature/DicomStudyTest.php
git commit -m "feat: add DicomStudy and DicomSeries models"
```

---

### Task 3: `DicomTagReader` — parse the ~9 header tags this feature needs

No mature PHP DICOM library exists (spec §9 risk) — this hand-rolled reader only needs to read simple-VR header elements up to (and stopping at) `PixelData`, which is a well-bounded, fully standard part of the DICOM file format regardless of how the pixel data itself is compressed.

**Files:**
- Create: `app/Services/DicomTagReader.php`
- Test: `tests/Unit/Services/DicomTagReaderTest.php`

- [ ] **Step 1: Write the failing test, including the raw-bytes DICOM fixture builder**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\DicomTagReader;
use PHPUnit\Framework\TestCase;

class DicomTagReaderTest extends TestCase
{
    /**
     * Builds a minimal, real Explicit-VR-Little-Endian DICOM byte stream
     * containing only the tags this reader cares about, terminated by a
     * PixelData element (so the reader has something to stop at, matching
     * a real file where PixelData is the last/largest element it will ever
     * need to look past).
     */
    private function buildFixture(array $overrides = []): string
    {
        $pad = fn (string $value) => strlen($value) % 2 === 0 ? $value : $value."\0";

        $writeElement = function (string $tag, string $vr, string $value) use ($pad) {
            $group = hexdec(substr($tag, 0, 4));
            $element = hexdec(substr($tag, 4, 4));
            $value = $pad($value);
            $bytes = pack('vv', $group, $element).$vr;
            $bytes .= in_array($vr, ['OB', 'OW'], true)
                ? "\0\0".pack('V', strlen($value))
                : pack('v', strlen($value));

            return $bytes.$value;
        };

        $elements = '';
        $elements .= $writeElement('00020010', 'UI', '1.2.840.10008.1.2.1');
        $elements .= $writeElement('0020000E', 'UI', $overrides['series_uid'] ?? '1.2.3.4.5.6');
        $elements .= $writeElement('00080060', 'CS', $overrides['modality'] ?? 'CT');
        $elements .= $writeElement('00080020', 'DA', $overrides['study_date'] ?? '20260901');
        $elements .= $writeElement('00081030', 'LO', $overrides['study_description'] ?? 'Full Arch Scan');
        $elements .= $writeElement('00280010', 'US', pack('v', $overrides['rows'] ?? 512));
        $elements .= $writeElement('00280011', 'US', pack('v', $overrides['columns'] ?? 512));
        $elements .= $writeElement('00280030', 'DS', $overrides['pixel_spacing'] ?? '0.3\\0.3');
        $elements .= $writeElement('00180050', 'DS', $overrides['slice_thickness'] ?? '0.5');
        $elements .= $writeElement('00200037', 'DS', $overrides['orientation'] ?? '1\\0\\0\\0\\1\\0');
        $elements .= $writeElement('7FE00010', 'OB', "\0\0\0\0"); // dummy pixel data, reader stops here

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    public function test_reads_the_tags_this_feature_needs(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        file_put_contents($path, $this->buildFixture());

        $tags = (new DicomTagReader())->read($path);
        unlink($path);

        $this->assertSame('1.2.3.4.5.6', $tags['series_uid']);
        $this->assertSame('CT', $tags['modality']);
        $this->assertSame('20260901', $tags['study_date']);
        $this->assertSame('Full Arch Scan', $tags['study_description']);
        $this->assertSame(512, $tags['rows']);
        $this->assertSame(512, $tags['columns']);
        $this->assertSame(0.3, $tags['pixel_spacing_x']);
        $this->assertSame(0.3, $tags['pixel_spacing_y']);
        $this->assertSame(0.5, $tags['slice_thickness']);
        $this->assertSame('1\\0\\0\\0\\1\\0', $tags['orientation']);
    }

    public function test_missing_optional_tags_come_back_null_instead_of_erroring(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicom_test_');
        // A file with only the required magic bytes and pixel data -- no
        // metadata tags at all, to prove partial/malformed-but-parseable
        // files don't crash the reader.
        file_put_contents($path, str_repeat("\0", 128).'DICM');

        $tags = (new DicomTagReader())->read($path);
        unlink($path);

        $this->assertNull($tags['series_uid']);
        $this->assertNull($tags['modality']);
    }

    public function test_rejects_a_file_without_the_dicm_magic_bytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_dicom_');
        file_put_contents($path, str_repeat('x', 200));

        $this->expectException(\InvalidArgumentException::class);
        (new DicomTagReader())->read($path);
        unlink($path);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/DicomTagReaderTest.php`
Expected: FAIL — `Class "App\Services\DicomTagReader" not found`

- [ ] **Step 3: Write `app/Services/DicomTagReader.php`**

```php
<?php

namespace App\Services;

/**
 * Reads only the handful of DICOM header tags this feature needs (series
 * identity + the geometry needed for MPR/volume reconstruction) -- it never
 * touches PixelData itself. Stops as soon as it reaches PixelData, since
 * everything needed always comes before it in a standard DICOM file.
 *
 * Supports Explicit VR Little Endian only (the transfer syntax used by the
 * File Meta group itself, always, and the overwhelming majority of real
 * CBCT/CT exports for the main dataset too). If a real-world export shows up
 * using Implicit VR Little Endian for its main dataset, extend readElement()
 * to branch on the Transfer Syntax UID read from tag 00020010 -- deliberately
 * not built until a real file proves it's needed (YAGNI).
 */
class DicomTagReader
{
    private const TAG_SERIES_INSTANCE_UID = '0020000E';
    private const TAG_MODALITY = '00080060';
    private const TAG_STUDY_DATE = '00080020';
    private const TAG_STUDY_DESCRIPTION = '00081030';
    private const TAG_ROWS = '00280010';
    private const TAG_COLUMNS = '00280011';
    private const TAG_PIXEL_SPACING = '00280030';
    private const TAG_SLICE_THICKNESS = '00180050';
    private const TAG_IMAGE_ORIENTATION_PATIENT = '00200037';
    private const TAG_PIXEL_DATA = '7FE00010';

    private const LONG_LENGTH_VRS = ['OB', 'OW', 'OF', 'SQ', 'UT', 'UN'];

    public function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$path}");
        }

        try {
            return $this->parse($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function parse($handle): array
    {
        fseek($handle, 128);
        if (fread($handle, 4) !== 'DICM') {
            throw new \InvalidArgumentException('Not a valid DICOM file (missing DICM magic bytes).');
        }

        $tags = [];

        while (! feof($handle)) {
            $element = $this->readElement($handle);
            if ($element === null) {
                break;
            }

            [$tag, $value] = $element;
            $tags[$tag] = $value;

            if ($tag === self::TAG_PIXEL_DATA) {
                break;
            }
        }

        $pixelSpacing = $this->splitBackslash($tags[self::TAG_PIXEL_SPACING] ?? null);

        return [
            'series_uid' => $this->trimmed($tags[self::TAG_SERIES_INSTANCE_UID] ?? null),
            'modality' => $this->trimmed($tags[self::TAG_MODALITY] ?? null),
            'study_date' => $this->trimmed($tags[self::TAG_STUDY_DATE] ?? null),
            'study_description' => $this->trimmed($tags[self::TAG_STUDY_DESCRIPTION] ?? null),
            'rows' => isset($tags[self::TAG_ROWS]) ? unpack('v', $tags[self::TAG_ROWS])[1] : null,
            'columns' => isset($tags[self::TAG_COLUMNS]) ? unpack('v', $tags[self::TAG_COLUMNS])[1] : null,
            'pixel_spacing_x' => isset($pixelSpacing[0]) ? (float) $pixelSpacing[0] : null,
            'pixel_spacing_y' => isset($pixelSpacing[1]) ? (float) $pixelSpacing[1] : null,
            'slice_thickness' => isset($tags[self::TAG_SLICE_THICKNESS]) ? (float) trim($tags[self::TAG_SLICE_THICKNESS]) : null,
            'orientation' => $this->trimmed($tags[self::TAG_IMAGE_ORIENTATION_PATIENT] ?? null),
        ];
    }

    /**
     * @param  resource  $handle
     */
    private function readElement($handle): ?array
    {
        $groupBytes = fread($handle, 2);
        $elementBytes = fread($handle, 2);
        if (strlen($groupBytes) < 2 || strlen($elementBytes) < 2) {
            return null;
        }

        $group = unpack('v', $groupBytes)[1];
        $element = unpack('v', $elementBytes)[1];
        $tag = strtoupper(sprintf('%04x%04x', $group, $element));

        $vr = fread($handle, 2);
        if (strlen($vr) < 2) {
            return null;
        }

        if (in_array($vr, self::LONG_LENGTH_VRS, true)) {
            fread($handle, 2); // reserved bytes
            $lengthBytes = fread($handle, 4);
            if (strlen($lengthBytes) < 4) {
                return null;
            }
            $length = unpack('V', $lengthBytes)[1];
        } else {
            $lengthBytes = fread($handle, 2);
            if (strlen($lengthBytes) < 2) {
                return null;
            }
            $length = unpack('v', $lengthBytes)[1];
        }

        if ($length === 0 || $length === 0xFFFFFFFF) {
            return [$tag, ''];
        }

        $value = fread($handle, $length);

        return [$tag, $value === false ? '' : $value];
    }

    private function trimmed(?string $value): ?string
    {
        return $value === null ? null : rtrim($value);
    }

    /**
     * @return string[]|null
     */
    private function splitBackslash(?string $value): ?array
    {
        return $value === null ? null : explode('\\', rtrim($value));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/DicomTagReaderTest.php`
Expected: PASS (3 tests). If a byte-offset assertion fails, the most likely cause is the VR-length-format branch (`LONG_LENGTH_VRS`) or an odd-length padding mismatch between the test fixture and the reader — add a `dump(bin2hex($tags[...]))`-style debug line at the failing assertion to inspect the raw bytes, fix, and re-run.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DicomTagReader.php tests/Unit/Services/DicomTagReaderTest.php
git commit -m "feat: add DicomTagReader for parsing DICOM header metadata"
```

---

### Task 4: Upload endpoint — `POST /api/dicom-studies`

Handles both a `.zip` upload and multiple loose `.dcm` files, groups files by `SeriesInstanceUID`, and creates the `dicom_studies`/`dicom_series` rows.

**Files:**
- Create: `app/Http/Requests/DicomStudy/StoreDicomStudyRequest.php`
- Create: `app/Http/Controllers/Api/DicomStudyController.php`
- Modify: `routes/api.php` (register routes, in the authenticated `sanctum` group near the `xray-images` routes)
- Test: `tests/Feature/DicomStudyUploadTest.php`

- [ ] **Step 1: Write the failing test**

Reuses the same `buildFixture()`-style helper as Task 3's unit test, but as a small local helper method (Feature tests live in a different namespace, so duplicate the ~15-line builder rather than sharing it across `tests/Unit` and `tests/Feature` — not worth a shared test-support class for one helper).

```php
<?php

namespace Tests\Feature;

use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class DicomStudyUploadTest extends TestCase
{
    use RefreshDatabase;

    private function buildDicomBytes(string $seriesUid, string $modality = 'CBCT'): string
    {
        $pad = fn (string $value) => strlen($value) % 2 === 0 ? $value : $value."\0";
        $writeElement = function (string $tag, string $vr, string $value) use ($pad) {
            $group = hexdec(substr($tag, 0, 4));
            $element = hexdec(substr($tag, 4, 4));
            $value = $pad($value);
            $bytes = pack('vv', $group, $element).$vr;
            $bytes .= in_array($vr, ['OB', 'OW'], true)
                ? "\0\0".pack('V', strlen($value))
                : pack('v', strlen($value));

            return $bytes.$value;
        };

        $elements = '';
        $elements .= $writeElement('0020000E', 'UI', $seriesUid);
        $elements .= $writeElement('00080060', 'CS', $modality);
        $elements .= $writeElement('00080020', 'DA', '20260901');
        $elements .= $writeElement('00081030', 'LO', 'Full Arch Scan');
        $elements .= $writeElement('00280010', 'US', pack('v', 512));
        $elements .= $writeElement('00280011', 'US', pack('v', 512));
        $elements .= $writeElement('00280030', 'DS', '0.3\\0.3');
        $elements .= $writeElement('00180050', 'DS', '0.5');
        $elements .= $writeElement('00200037', 'DS', '1\\0\\0\\0\\1\\0');
        $elements .= $writeElement('7FE00010', 'OB', "\0\0\0\0");

        return str_repeat("\0", 128).'DICM'.$elements;
    }

    private function activeDoctor(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        \App\Models\Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);

        return $doctor;
    }

    public function test_uploading_loose_dcm_files_from_one_series_creates_one_study_and_series(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $slice1 = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-A'));
        $slice2 = UploadedFile::fake()->createWithContent('slice2.dcm', $this->buildDicomBytes('1.2.3.SERIES-A'));

        $response = $this->post('/api/dicom-studies', ['files' => [$slice1, $slice2]]);

        $response->assertCreated();
        $this->assertDatabaseCount('dicom_studies', 1);
        $this->assertDatabaseHas('dicom_studies', ['status' => 'ready', 'modality' => 'CBCT', 'slice_count' => 2]);
        $this->assertDatabaseHas('dicom_series', ['series_uid' => '1.2.3.SERIES-A', 'slice_count' => 2]);
    }

    public function test_uploading_a_zip_extracts_and_groups_by_series(): void
    {
        Sanctum::actingAs($this->activeDoctor());

        $zipPath = tempnam(sys_get_temp_dir(), 'dicom_zip_').'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-B'));
        $zip->addFromString('slice2.dcm', $this->buildDicomBytes('1.2.3.SERIES-B'));
        $zip->addFromString('readme.txt', 'not a dicom file'); // must be ignored, not crash the upload
        $zip->close();

        $response = $this->post('/api/dicom-studies', [
            'archive' => new UploadedFile($zipPath, 'study.zip', 'application/zip', null, true),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dicom_series', ['series_uid' => '1.2.3.SERIES-B', 'slice_count' => 2]);
        unlink($zipPath);
    }

    public function test_a_study_can_be_uploaded_already_linked_to_a_client(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = \App\Models\Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-2001',
            'name' => 'Linked Patient',
            'phone' => '+15550002222',
            'gender' => 'female',
            'status' => 'new',
        ]);

        $slice = UploadedFile::fake()->createWithContent('slice1.dcm', $this->buildDicomBytes('1.2.3.SERIES-C'));
        $response = $this->post('/api/dicom-studies', ['files' => [$slice], 'client_id' => $client->id]);

        $response->assertCreated();
        $this->assertSame($client->id, DicomStudy::first()->client_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DicomStudyUploadTest.php`
Expected: FAIL — 404 (route doesn't exist yet)

- [ ] **Step 3: Write `app/Http/Requests/DicomStudy/StoreDicomStudyRequest.php`**

```php
<?php

namespace App\Http\Requests\DicomStudy;

use Illuminate\Foundation\Http\FormRequest;

class StoreDicomStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archive' => ['required_without:files', 'file', 'mimes:zip'],
            'files' => ['required_without:archive', 'array', 'min:1'],
            'files.*' => ['file'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
```

- [ ] **Step 4: Write `app/Http/Controllers/Api/DicomStudyController.php`** (store only for now — index/update/destroy come in later tasks)

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DicomStudy\StoreDicomStudyRequest;
use App\Models\Client;
use App\Models\DicomSeries;
use App\Models\DicomStudy;
use App\Services\DicomTagReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DicomStudyController extends Controller
{
    public function __construct(protected DicomTagReader $tagReader) {}

    public function store(StoreDicomStudyRequest $request)
    {
        $data = $request->validated();
        $clientId = $this->resolveClientId($data['client_id'] ?? null);

        $dicomFilePaths = isset($data['archive'])
            ? $this->extractZip($request->file('archive'))
            : $this->saveLooseFiles($request->file('files'));

        $bySeriesUid = [];
        foreach ($dicomFilePaths as $tempPath => $originalName) {
            try {
                $tags = $this->tagReader->read($tempPath);
            } catch (\InvalidArgumentException) {
                continue; // not a real DICOM file (e.g. a readme.txt inside a zip) -- skip it
            }

            $seriesUid = $tags['series_uid'] ?? 'unknown-series';
            $bySeriesUid[$seriesUid]['tags'] = $tags;
            $bySeriesUid[$seriesUid]['files'][] = $tempPath;
        }

        if (empty($bySeriesUid)) {
            throw ValidationException::withMessages([
                'files' => ['No valid DICOM files were found in the upload.'],
            ]);
        }

        $study = $request->user()->company->dicomStudies()->create([
            'client_id' => $clientId,
            'uploaded_by' => $request->user()->id,
            'modality' => reset($bySeriesUid)['tags']['modality'] ?? null,
            'study_date' => reset($bySeriesUid)['tags']['study_date'] ?? null,
            'description' => reset($bySeriesUid)['tags']['study_description'] ?? null,
            'slice_count' => array_sum(array_map(fn ($series) => count($series['files']), $bySeriesUid)),
            'status' => 'ready',
        ]);

        foreach ($bySeriesUid as $seriesUid => $series) {
            $storagePath = "dicom-studies/{$study->uuid}/{$seriesUid}";

            foreach ($series['files'] as $index => $tempPath) {
                Storage::disk('public')->putFileAs($storagePath, $tempPath, "{$index}.dcm");
                @unlink($tempPath);
            }

            $study->series()->create([
                'series_uid' => $seriesUid,
                'rows' => $series['tags']['rows'] ?? null,
                'columns' => $series['tags']['columns'] ?? null,
                'slice_count' => count($series['files']),
                'pixel_spacing_x' => $series['tags']['pixel_spacing_x'] ?? null,
                'pixel_spacing_y' => $series['tags']['pixel_spacing_y'] ?? null,
                'slice_thickness' => $series['tags']['slice_thickness'] ?? null,
                'orientation' => $series['tags']['orientation'] ?? null,
                'storage_path' => $storagePath,
            ]);
        }

        return $this->success($study->load('series'), 'Study uploaded successfully.', 201);
    }

    /**
     * @return array<string, string> temp file path => original filename
     */
    protected function extractZip($archiveFile): array
    {
        $zip = new ZipArchive();
        $zip->open($archiveFile->getRealPath());

        $extractDir = storage_path('app/dicom-uploads/'.uniqid());
        $zip->extractTo($extractDir);
        $zip->close();

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[$file->getPathname()] = $file->getFilename();
            }
        }

        return $paths;
    }

    /**
     * @return array<string, string> temp file path => original filename
     */
    protected function saveLooseFiles(array $files): array
    {
        $paths = [];
        foreach ($files as $file) {
            $tempPath = $file->getRealPath();
            $paths[$tempPath] = $file->getClientOriginalName();
        }

        return $paths;
    }

    /**
     * Same tenant-isolation reasoning as XrayImageController::resolveClientId()
     * -- the FormRequest's exists:clients,id check doesn't see Client's
     * BelongsToCompany scope, so re-resolve through the scoped model here.
     */
    protected function resolveClientId(?int $clientId): ?int
    {
        if ($clientId === null) {
            return null;
        }

        if (! Client::query()->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages([
                'client_id' => ['Please select a valid client.'],
            ]);
        }

        return $clientId;
    }
}
```

- [ ] **Step 5: Register the route in `routes/api.php`**

Find the existing `xray-images` route group (search for `XrayImageController`) and add immediately after it, inside the same authenticated `sanctum` middleware group:

```php
    Route::post('dicom-studies', [DicomStudyController::class, 'store']);
```

Add the import near the other `Api\` controller imports at the top of the file:

```php
use App\Http\Controllers\Api\DicomStudyController;
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/DicomStudyUploadTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/DicomStudy/StoreDicomStudyRequest.php app/Http/Controllers/Api/DicomStudyController.php routes/api.php tests/Feature/DicomStudyUploadTest.php
git commit -m "feat: add DICOM study upload endpoint (zip and loose files)"
```

---

### Task 5: `DicomStudyResource` and the `index`/`show` endpoints

**Files:**
- Create: `app/Http/Resources/DicomStudyResource.php`
- Create: `app/Http/Resources/DicomSeriesResource.php`
- Modify: `app/Http/Controllers/Api/DicomStudyController.php` (add `index`, `show`)
- Modify: `routes/api.php`
- Test: `tests/Feature/DicomStudyIndexTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_companys_own_studies_with_series_and_client_name(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-3001',
            'name' => 'Gallery Patient',
            'phone' => '+15550003333',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $study = DicomStudy::create([
            'client_id' => $client->id,
            'uploaded_by' => $doctor->id,
            'modality' => 'CBCT',
            'status' => 'ready',
        ]);
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 10,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/dicom-studies');

        $response->assertOk();
        $response->assertJsonPath('data.0.client_name', 'Gallery Patient');
        $response->assertJsonPath('data.0.series.0.series_uid', '1.2.3');
    }

    public function test_index_excludes_another_companys_studies(): void
    {
        $otherCompany = Company::factory()->create();
        DicomStudy::create([
            'company_id' => $otherCompany->id,
            'uploaded_by' => User::factory()->create(['company_id' => $otherCompany->id])->id,
            'status' => 'ready',
        ]);
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);

        $response = $this->getJson('/api/dicom-studies');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_can_filter_to_unlinked_studies_only(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        Sanctum::actingAs($doctor);
        DicomStudy::create(['uploaded_by' => $doctor->id, 'status' => 'ready', 'client_id' => null]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-3002',
            'name' => 'Linked',
            'phone' => '+15550003334',
            'gender' => 'male',
            'status' => 'new',
        ]);
        DicomStudy::create(['uploaded_by' => $doctor->id, 'status' => 'ready', 'client_id' => $client->id]);

        $response = $this->getJson('/api/dicom-studies?unlinked=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DicomStudyIndexTest.php`
Expected: FAIL — 404

- [ ] **Step 3: Write `app/Http/Resources/DicomSeriesResource.php`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DicomSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'series_uid' => $this->series_uid,
            'rows' => $this->rows,
            'columns' => $this->columns,
            'slice_count' => $this->slice_count,
            'pixel_spacing_x' => $this->pixel_spacing_x,
            'pixel_spacing_y' => $this->pixel_spacing_y,
            'slice_thickness' => $this->slice_thickness,
            'orientation' => $this->orientation,
            'storage_path' => $this->storage_path,
        ];
    }
}
```

- [ ] **Step 4: Write `app/Http/Resources/DicomStudyResource.php`**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DicomStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'modality' => $this->modality,
            'study_date' => $this->study_date?->toDateString(),
            'description' => $this->description,
            'slice_count' => $this->slice_count,
            'status' => $this->status,
            'series' => DicomSeriesResource::collection($this->whenLoaded('series')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Add `index` and `show` to `DicomStudyController`**

Add these two methods (e.g. above `store`):

```php
    public function index(\Illuminate\Http\Request $request)
    {
        $studies = $request->user()->company->dicomStudies()
            ->with(['client', 'series'])
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('client_id'))
            ->latest()
            ->get();

        return $this->success(\App\Http\Resources\DicomStudyResource::collection($studies));
    }

    public function show(DicomStudy $dicomStudy)
    {
        return $this->success(\App\Http\Resources\DicomStudyResource::make($dicomStudy->load(['client', 'series'])));
    }
```

(These use fully-qualified class names inline deliberately to keep this step's diff self-contained; when applying, add proper `use` imports at the top of the file instead — `App\Http\Resources\DicomStudyResource`, `App\Http\Resources\DicomSeriesResource`, and `Illuminate\Http\Request` — and switch the inline references to the short class names.)

- [ ] **Step 6: Register the routes in `routes/api.php`**

```php
    Route::get('dicom-studies', [DicomStudyController::class, 'index']);
    Route::get('dicom-studies/{dicomStudy}', [DicomStudyController::class, 'show']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/DicomStudyIndexTest.php`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Resources/DicomStudyResource.php app/Http/Resources/DicomSeriesResource.php app/Http/Controllers/Api/DicomStudyController.php routes/api.php tests/Feature/DicomStudyIndexTest.php
git commit -m "feat: add DICOM study index/show endpoints"
```

---

### Task 6: Link a study to a client, and delete a study

**Files:**
- Create: `app/Http/Requests/DicomStudy/UpdateDicomStudyRequest.php`
- Modify: `app/Http/Controllers/Api/DicomStudyController.php` (add `update`, `destroy`)
- Modify: `routes/api.php`
- Test: `tests/Feature/DicomStudyLinkingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DicomStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DicomStudyLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_study_can_be_linked_to_a_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $client = Client::create([
            'company_id' => $doctor->company_id,
            'client_code' => 'CL-4001',
            'name' => 'Linkable',
            'phone' => '+15550004444',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $study = DicomStudy::create(['uploaded_by' => $doctor->id, 'status' => 'ready']);
        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/dicom-studies/{$study->id}", ['client_id' => $client->id]);

        $response->assertOk();
        $this->assertSame($client->id, $study->fresh()->client_id);
    }

    public function test_a_study_cannot_be_linked_to_another_companys_client(): void
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $otherClient = Client::factory()->create();
        $study = DicomStudy::create(['uploaded_by' => $doctor->id, 'status' => 'ready']);
        Sanctum::actingAs($doctor);

        $response = $this->putJson("/api/dicom-studies/{$study->id}", ['client_id' => $otherClient->id]);

        $response->assertStatus(422);
    }

    public function test_deleting_a_study_removes_its_stored_files(): void
    {
        Storage::fake('public');
        $doctor = User::factory()->create(['is_doctor' => true]);
        $study = DicomStudy::create(['uploaded_by' => $doctor->id, 'status' => 'ready']);
        $study->series()->create([
            'series_uid' => '1.2.3',
            'slice_count' => 1,
            'storage_path' => "dicom-studies/{$study->uuid}/1.2.3",
        ]);
        Storage::disk('public')->put("dicom-studies/{$study->uuid}/1.2.3/0.dcm", 'fake-bytes');
        Sanctum::actingAs($doctor);

        $response = $this->deleteJson("/api/dicom-studies/{$study->id}");

        $response->assertOk();
        Storage::disk('public')->assertMissing("dicom-studies/{$study->uuid}/1.2.3/0.dcm");
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/DicomStudyLinkingTest.php`
Expected: FAIL — 404 on the PUT/DELETE routes

- [ ] **Step 3: Write `app/Http/Requests/DicomStudy/UpdateDicomStudyRequest.php`**

```php
<?php

namespace App\Http\Requests\DicomStudy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDicomStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Add `update` and `destroy` to `DicomStudyController`**

```php
    public function update(\App\Http\Requests\DicomStudy\UpdateDicomStudyRequest $request, DicomStudy $dicomStudy)
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $data['client_id'] = $this->resolveClientId($data['client_id']);
        }

        $dicomStudy->update($data);

        return $this->success(\App\Http\Resources\DicomStudyResource::make($dicomStudy->fresh(['client', 'series'])), 'Study updated successfully.');
    }

    public function destroy(DicomStudy $dicomStudy)
    {
        foreach ($dicomStudy->series as $series) {
            Storage::disk('public')->deleteDirectory($series->storage_path);
        }

        $dicomStudy->delete();

        return $this->success(null, 'Study deleted successfully.');
    }
```

(As in Task 5, add proper `use` imports rather than inline fully-qualified names when applying this.)

- [ ] **Step 5: Register the routes in `routes/api.php`**

```php
    Route::put('dicom-studies/{dicomStudy}', [DicomStudyController::class, 'update']);
    Route::delete('dicom-studies/{dicomStudy}', [DicomStudyController::class, 'destroy']);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/DicomStudyLinkingTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Run the full backend suite to confirm no regressions**

Run: `php artisan test`
Expected: all tests pass, including the pre-existing suite

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/DicomStudy/UpdateDicomStudyRequest.php app/Http/Controllers/Api/DicomStudyController.php routes/api.php tests/Feature/DicomStudyLinkingTest.php
git commit -m "feat: add DICOM study linking and deletion endpoints"
```

---

### Task 7: Frontend API client methods

**Files:**
- Modify: `Dental_FrontEnd/app/frontend/src/lib/api.js`

- [ ] **Step 1: Add a `dicomStudies` client object**

Add this near the existing `xrayImages` object (end of the file, same shape):

```js
  dicomStudies: {
    list: (query, token) =>
      request("/dicom-studies", { token, query }),
    show: (studyId, token) =>
      request(`/dicom-studies/${studyId}`, { token }),
    // Multipart: EITHER `archive` (a single .zip File) OR `files` (an array
    // of loose .dcm File objects) must be set on the FormData, plus an
    // optional client_id.
    upload: (formData, token) =>
      requestMultipart("/dicom-studies", { formData, token }),
    update: (studyId, payload, token) =>
      request(`/dicom-studies/${studyId}`, { method: "PUT", body: payload, token }),
    delete: (studyId, token) =>
      request(`/dicom-studies/${studyId}`, { method: "DELETE", token }),
  },
```

- [ ] **Step 2: No automated test for this file** (it's a thin fetch wrapper with no logic of its own, matching how `xrayImages`/every other entry in this file is untested — verified instead by the pages that use it in later tasks)

- [ ] **Step 3: Commit**

```bash
cd Dental_FrontEnd
git add app/frontend/src/lib/api.js
git commit -m "feat: add dicomStudies API client methods"
```

---

### Task 8: Translation keys

**Files:**
- Modify: `Dental_FrontEnd/app/translations/en.json`, `tr.json`, `ar.json`

- [ ] **Step 1: Add the new keys to `en.json`** (near the existing `xrayImages`/`xrayGalleryTitle` keys)

```json
    "dicomStudies":  "CBCT/3D Scans",
    "dicomGalleryTitle":  "CBCT/3D Scan Gallery",
    "dicomUploadZip":  "Upload ZIP",
    "dicomUploadFiles":  "Upload DICOM Files",
    "dicomUnlinkedOnly":  "Unlinked only",
    "dicomNoScansFound":  "No scans found",
    "dicomNoLinkedScans":  "No scans linked to this patient yet",
    "dicomUnlinked":  "Unlinked",
    "dicomLinkToPatient":  "Link to patient",
    "dicomDeleteScan":  "Delete Scan",
    "dicomDeleteScanConfirm":  "Delete this scan? This cannot be undone.",
    "dicomOpenViewer":  "Open Viewer",
    "dicomModality":  "Modality",
    "dicomSliceCount":  "Slices",
    "dicomProcessing":  "Processing...",
```

- [ ] **Step 2: Add the Turkish equivalents to `tr.json`**

```json
  "dicomStudies": "CBCT/3D Taramalar",
  "dicomGalleryTitle": "CBCT/3D Tarama Galerisi",
  "dicomUploadZip": "ZIP Yükle",
  "dicomUploadFiles": "DICOM Dosyaları Yükle",
  "dicomUnlinkedOnly": "Sadece bağlanmamış",
  "dicomNoScansFound": "Tarama bulunamadı",
  "dicomNoLinkedScans": "Bu hastaya bağlı tarama yok",
  "dicomUnlinked": "Bağlanmamış",
  "dicomLinkToPatient": "Hastaya bağla",
  "dicomDeleteScan": "Taramayı Sil",
  "dicomDeleteScanConfirm": "Bu tarama silinsin mi? Geri alınamaz.",
  "dicomOpenViewer": "Görüntüleyiciyi Aç",
  "dicomModality": "Modalite",
  "dicomSliceCount": "Kesit Sayısı",
  "dicomProcessing": "İşleniyor...",
```

- [ ] **Step 3: Add the Arabic equivalents to `ar.json`**

```json
  "dicomStudies": "فحوصات CBCT/ثلاثية الأبعاد",
  "dicomGalleryTitle": "معرض فحوصات CBCT/ثلاثية الأبعاد",
  "dicomUploadZip": "رفع ملف ZIP",
  "dicomUploadFiles": "رفع ملفات DICOM",
  "dicomUnlinkedOnly": "غير المرتبطة فقط",
  "dicomNoScansFound": "لم يتم العثور على فحوصات",
  "dicomNoLinkedScans": "لا توجد فحوصات مرتبطة بهذا المريض بعد",
  "dicomUnlinked": "غير مرتبط",
  "dicomLinkToPatient": "ربط بالمريض",
  "dicomDeleteScan": "حذف الفحص",
  "dicomDeleteScanConfirm": "هل تريد حذف هذا الفحص؟ لا يمكن التراجع عن هذا الإجراء.",
  "dicomOpenViewer": "فتح العارض",
  "dicomModality": "الطريقة",
  "dicomSliceCount": "عدد المقاطع",
  "dicomProcessing": "جارٍ المعالجة...",
```

- [ ] **Step 4: Commit**

```bash
cd Dental_FrontEnd
git add app/translations/en.json app/translations/tr.json app/translations/ar.json
git commit -m "feat: add DICOM viewer translation keys"
```

---

### Task 9: Install Cornerstone3D packages

**Files:**
- Modify: `Dental_FrontEnd/app/frontend/package.json` (via npm, not hand-edited)

- [ ] **Step 1: Install the packages**

Run (from `Dental_FrontEnd/app/frontend`):

```bash
npm install @cornerstonejs/core @cornerstonejs/tools @cornerstonejs/dicom-image-loader dicom-parser
```

Expected: install succeeds, `package.json`/`package-lock.json` updated.

- [ ] **Step 2: Confirm the build still works with the new dependencies present but unused**

Run: `npm run build`
Expected: builds clean (these packages aren't imported by anything yet, so this just confirms the install didn't break anything)

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install Cornerstone3D for DICOM viewing"
```

---

### Task 10: `DicomGalleryPage.jsx` — company-wide gallery + upload

Mirrors `XrayGalleryPage.jsx` exactly (read that file first — same structure, same patterns, different endpoint and fields).

**Files:**
- Create: `Dental_FrontEnd/app/frontend/src/pages/DicomGalleryPage.jsx`

- [ ] **Step 1: Write the component**

```jsx
import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "../lib/api";
import { useAppState } from "../context/useAppState";
import { useLanguage } from "../context/useLanguage";

export default function DicomGalleryPage() {
  const { authToken } = useAppState();
  const { t } = useLanguage();

  const [studies, setStudies] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [deletingId, setDeletingId] = useState("");
  const [unlinkedOnly, setUnlinkedOnly] = useState(false);
  const zipInputRef = useRef(null);
  const filesInputRef = useRef(null);

  const load = useCallback(async () => {
    if (!authToken) return;
    setIsLoading(true);
    try {
      const result = await api.dicomStudies.list({}, authToken);
      setStudies(Array.isArray(result) ? result : []);
    } finally {
      setIsLoading(false);
    }
  }, [authToken]);

  useEffect(() => {
    void load();
  }, [load]);

  const visibleStudies = unlinkedOnly ? studies.filter((study) => !study.client_id) : studies;

  const handleUploadZip = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    try {
      const formData = new FormData();
      formData.append("archive", file);
      await api.dicomStudies.upload(formData, authToken);
      void load();
    } finally {
      setIsUploading(false);
      if (zipInputRef.current) zipInputRef.current.value = "";
    }
  };

  const handleUploadFiles = async (event) => {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;

    setIsUploading(true);
    try {
      const formData = new FormData();
      files.forEach((file) => formData.append("files[]", file));
      await api.dicomStudies.upload(formData, authToken);
      void load();
    } finally {
      setIsUploading(false);
      if (filesInputRef.current) filesInputRef.current.value = "";
    }
  };

  const handleDelete = async (study) => {
    if (!window.confirm(t("dicomDeleteScanConfirm"))) return;

    setDeletingId(String(study.id));
    try {
      await api.dicomStudies.delete(study.id, authToken);
      void load();
    } finally {
      setDeletingId("");
    }
  };

  return (
    <section className="page-grid">
      <section className="table-card">
        <div className="section-heading section-heading-split">
          <div>
            <span>{t("dicomStudies")}</span>
            <h2>{t("dicomGalleryTitle")}</h2>
          </div>
          <div className="row-actions">
            <label className={`ghost-button ${isUploading ? "disabled" : ""}`}>
              {isUploading ? t("dicomProcessing") : t("dicomUploadZip")}
              <input ref={zipInputRef} type="file" accept=".zip" hidden onChange={(event) => void handleUploadZip(event)} disabled={isUploading} />
            </label>
            <label className={`primary-button ${isUploading ? "disabled" : ""}`}>
              {isUploading ? t("dicomProcessing") : t("dicomUploadFiles")}
              <input ref={filesInputRef} type="file" accept=".dcm" multiple hidden onChange={(event) => void handleUploadFiles(event)} disabled={isUploading} />
            </label>
          </div>
        </div>

        <label className="xray-unlinked-toggle">
          <input type="checkbox" checked={unlinkedOnly} onChange={(event) => setUnlinkedOnly(event.target.checked)} />
          <span>{t("dicomUnlinkedOnly")}</span>
        </label>

        {isLoading ? <p className="empty-state">{t("loading")}</p> : null}
        {!isLoading && visibleStudies.length === 0 ? <p className="empty-state">{t("dicomNoScansFound")}</p> : null}

        {!isLoading && visibleStudies.length > 0 ? (
          <div className="xray-image-grid xray-image-grid-wide">
            {visibleStudies.map((study) => (
              <div key={study.id} className="xray-gallery-card">
                <div className="xray-gallery-card-footer">
                  <span>{study.modality || "-"}</span>
                  <span>{study.slice_count} {t("dicomSliceCount")}</span>
                </div>
                <div className="xray-gallery-card-footer">
                  <span className={`status-pill ${study.client_id ? "active" : "muted"}`}>
                    {study.client_id ? study.client_name : t("dicomUnlinked")}
                  </span>
                  <button
                    type="button"
                    className="icon-button danger"
                    onClick={() => void handleDelete(study)}
                    aria-label={t("dicomDeleteScan")}
                    title={t("dicomDeleteScan")}
                    disabled={deletingId === String(study.id)}
                  >
                    ×
                  </button>
                </div>
              </div>
            ))}
          </div>
        ) : null}
      </section>
    </section>
  );
}
```

- [ ] **Step 2: Register the route in `App.jsx`**

Add the import near `XrayGalleryPage`'s import:

```js
import DicomGalleryPage from "./pages/DicomGalleryPage";
```

Add the route near `/xray-images`:

```jsx
              <Route path="/dicom-studies" element={<DicomGalleryPage />} />
```

- [ ] **Step 3: Add the nav entry in `mockData.js`**

Find the `"company-group"` children array (containing the `xray-images` entry) in `Dental_FrontEnd/app/frontend/src/data/mockData.js` and add immediately after it:

```js
      { id: "dicom-studies", labelKey: "dicomStudies", label: "CBCT/3D Scans", path: "/dicom-studies", group: "admin", icon: "?" },
```

- [ ] **Step 4: Build and manually verify**

Run: `npm run build` (expect clean build), `npm run lint` (expect no new warnings in `DicomGalleryPage.jsx`)

Then use the `run` skill to start the app and confirm in a real browser: the "CBCT/3D Scans" nav item appears, the gallery page loads (empty state), and the two upload inputs are present. Full upload verification (a real DICOM file) happens in Task 12 once the client-details tab and picker exist too — note in your final report whether this step's browser check was actually performed or not (per this project's own verification standards).

- [ ] **Step 5: Commit**

```bash
git add app/frontend/src/pages/DicomGalleryPage.jsx app/frontend/src/App.jsx app/frontend/src/data/mockData.js
git commit -m "feat: add CBCT/3D scan gallery page"
```

---

### Task 11: `DicomStudyPicker.jsx` — per-client linked list, for the Client Details tab

Mirrors `XrayImagePicker.jsx`'s "linked" mode only (no "browse studio" mode needed for v1 — YAGNI; add it later if the user asks for the same "attach an existing unlinked study from here" flow X-ray has).

**Files:**
- Create: `Dental_FrontEnd/app/frontend/src/components/DicomStudyPicker.jsx`

- [ ] **Step 1: Write the component**

```jsx
import { useCallback, useEffect, useState } from "react";
import { api } from "../lib/api";
import { useAppState } from "../context/useAppState";
import { useLanguage } from "../context/useLanguage";
import DicomViewer from "./DicomViewer";

// Per the design spec's access-control rule (same as X-ray/AI): any staff
// member can see this list and link/unlink studies, but only a doctor or
// System Manager can actually open the interactive viewer.
export default function DicomStudyPicker() {
  const { authToken, selectedClient, authUser, isSystemManager } = useAppState();
  const { t } = useLanguage();
  const canOpenViewer = Boolean(authUser?.isDoctor) || isSystemManager;

  const [studies, setStudies] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [openStudyId, setOpenStudyId] = useState(null);

  const load = useCallback(async () => {
    if (!authToken || !selectedClient) return;
    setIsLoading(true);
    try {
      const result = await api.dicomStudies.list({ client_id: selectedClient.id }, authToken);
      setStudies(Array.isArray(result) ? result : []);
    } finally {
      setIsLoading(false);
    }
  }, [authToken, selectedClient]);

  useEffect(() => {
    void load();
  }, [load]);

  if (isLoading) return <p className="empty-state">{t("loading")}</p>;
  if (!studies.length) return <p className="empty-state">{t("dicomNoLinkedScans")}</p>;

  return (
    <div className="xray-picker">
      {studies.map((study) => (
        <div key={study.id} className="detail-card">
          <div className="section-heading section-heading-split">
            <div>
              <span>{study.modality} — {study.study_date || "-"}</span>
              <h2>{study.description || t("dicomStudies")}</h2>
            </div>
            {canOpenViewer ? (
              <button type="button" className="ghost-button" onClick={() => setOpenStudyId(openStudyId === study.id ? null : study.id)}>
                {openStudyId === study.id ? t("close") : t("dicomOpenViewer")}
              </button>
            ) : null}
          </div>
          {canOpenViewer && openStudyId === study.id ? <DicomViewer study={study} /> : null}
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Commit** (this won't build yet — `DicomViewer` doesn't exist until Task 13 — that's expected and fine mid-plan; do not run the build check until Task 13 is done)

```bash
git add app/frontend/src/components/DicomStudyPicker.jsx
git commit -m "feat: add DicomStudyPicker for the Client Details CBCT tab"
```

---

### Task 12: Add the "CBCT" tab to `ClientDetailsPage.jsx`

Same mechanism as the "ai"/"xray" tabs added earlier this project — append to the tabs array, add a render block, no popup.

**Files:**
- Modify: `Dental_FrontEnd/app/frontend/src/pages/ClientDetailsPage.jsx`

- [ ] **Step 1: Import `DicomStudyPicker`**

```js
import DicomStudyPicker from "../components/DicomStudyPicker";
```

- [ ] **Step 2: Add the tab entry**

In the tabs array (the one ending in the `ai`/`xray` entries added previously), add one more after `xray`:

```js
            { id: "xray", label: t("xrayImages") },
            { id: "dicom", label: t("dicomStudies") },
```

- [ ] **Step 3: Add the render block**

Immediately after the existing `{activePanel === "xray" ? (...) : null}` block:

```jsx
      {activePanel === "dicom" ? (
        <article className="detail-card">
          <div className="section-heading">
            <span>{selectedClient.name}</span>
            <h2>{t("dicomStudies")}</h2>
          </div>
          <DicomStudyPicker />
        </article>
      ) : null}
```

- [ ] **Step 4: Repeat steps 1-3 for the four specialty equivalents**

`Dental_FrontEnd/app/frontend/src/specialties/{cosmetic,orthopedics,internal_medicine,gynecology}/pages/*ClientDetailsPage.jsx` — same three edits, same code, in each of the four files (per the design spec, all 5 specialties get this feature).

- [ ] **Step 5: Commit**

```bash
git add app/frontend/src/pages/ClientDetailsPage.jsx app/frontend/src/specialties/*/pages/*ClientDetailsPage.jsx
git commit -m "feat: add CBCT tab to Client Details across all 5 specialties"
```

---

### Task 13: `DicomViewer.jsx` — the 4-pane Cornerstone3D viewport

**This is the one task in this plan integrating a library whose exact current API this plan cannot fully pin down from memory alone** (Cornerstone3D's import paths and setup calls have changed across versions historically). The architecture below — a `RenderingEngine` owning multiple `Viewport`s, `wadouri:` image IDs feeding a stack, a `Volume` built from those same image IDs feeding the 3D/MPR viewports — is the stable, well-documented Cornerstone3D pattern. Before writing the component, open `Dental_FrontEnd/app/frontend/node_modules/@cornerstonejs/core/dist/esm/index.d.ts` (installed in Task 9) and confirm the exact export names (`RenderingEngine`, `Enums.ViewportType`, `imageLoader.registerImageLoader`, `init` function) match what's used below — adjust names to match the installed version, the shape of the integration should not need to change.

**Files:**
- Create: `Dental_FrontEnd/app/frontend/src/components/DicomViewer.jsx`
- Create: `Dental_FrontEnd/app/frontend/src/utils/dicomViewerSetup.js`

- [ ] **Step 1: Write `dicomViewerSetup.js`** — one-time Cornerstone3D initialization, called once per app lifetime

```js
import { init as coreInit, imageLoader, RenderingEngine } from "@cornerstonejs/core";
import { init as toolsInit } from "@cornerstonejs/tools";
import * as cornerstoneDicomImageLoader from "@cornerstonejs/dicom-image-loader";
import dicomParser from "dicom-parser";

let isInitialized = false;

export async function ensureCornerstoneInitialized() {
  if (isInitialized) return;

  await coreInit();
  toolsInit();

  cornerstoneDicomImageLoader.external.dicomParser = dicomParser;
  imageLoader.registerImageLoader("wadouri", cornerstoneDicomImageLoader.wadouri.loadImage);

  isInitialized = true;
}

export function buildImageIds(series) {
  // One wadouri: image ID per stored slice file -- matches how store()
  // wrote them (0.dcm, 1.dcm, ... per series.slice_count), served straight
  // off the public disk (same URL-building convention as XrayImageResource).
  const baseUrl = `${window.location.origin}/storage/${series.storage_path}`;
  return Array.from({ length: series.slice_count }, (_, index) => `wadouri:${baseUrl}/${index}.dcm`);
}

export { RenderingEngine };
```

- [ ] **Step 2: Write `DicomViewer.jsx`**

```jsx
import { useEffect, useId, useRef, useState } from "react";
import { Enums } from "@cornerstonejs/core";
import { buildImageIds, ensureCornerstoneInitialized, RenderingEngine } from "../utils/dicomViewerSetup";

export default function DicomViewer({ study }) {
  const renderingEngineId = useId();
  const axialRef = useRef(null);
  const sagittalRef = useRef(null);
  const coronalRef = useRef(null);
  const volumeRef = useRef(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let renderingEngine;
    let cancelled = false;

    (async () => {
      try {
        await ensureCornerstoneInitialized();
        if (cancelled) return;

        const series = study.series?.[0];
        if (!series) {
          setError("This study has no series to display.");
          return;
        }

        const imageIds = buildImageIds(series);
        renderingEngine = new RenderingEngine(renderingEngineId);

        const viewportInputs = [
          { viewportId: "AXIAL", type: Enums.ViewportType.ORTHOGRAPHIC, element: axialRef.current, defaultOptions: { orientation: Enums.OrientationAxis.AXIAL } },
          { viewportId: "SAGITTAL", type: Enums.ViewportType.ORTHOGRAPHIC, element: sagittalRef.current, defaultOptions: { orientation: Enums.OrientationAxis.SAGITTAL } },
          { viewportId: "CORONAL", type: Enums.ViewportType.ORTHOGRAPHIC, element: coronalRef.current, defaultOptions: { orientation: Enums.OrientationAxis.CORONAL } },
          { viewportId: "VOLUME_3D", type: Enums.ViewportType.VOLUME_3D, element: volumeRef.current, defaultOptions: {} },
        ];

        renderingEngine.setViewports(viewportInputs);

        const volumeId = `cornerstoneStreamingImageVolume:${study.uuid}`;
        const { volumeLoader } = await import("@cornerstonejs/core");
        const volume = await volumeLoader.createAndCacheVolume(volumeId, { imageIds });
        await volume.load();

        const { setVolumesForViewports } = await import("@cornerstonejs/core");
        await setVolumesForViewports(renderingEngine, [{ volumeId }], ["AXIAL", "SAGITTAL", "CORONAL", "VOLUME_3D"]);

        renderingEngine.render();
      } catch (caughtError) {
        if (!cancelled) setError(caughtError?.message || "Failed to load this scan.");
      }
    })();

    return () => {
      cancelled = true;
      renderingEngine?.destroy();
    };
  }, [study, renderingEngineId]);

  if (error) return <p className="empty-state">{error}</p>;

  return (
    <div className="dicom-viewer-grid">
      <div ref={axialRef} className="dicom-viewport" />
      <div ref={sagittalRef} className="dicom-viewport" />
      <div ref={coronalRef} className="dicom-viewport" />
      <div ref={volumeRef} className="dicom-viewport" />
    </div>
  );
}
```

- [ ] **Step 3: Add the 2×2 grid CSS**

In `Dental_FrontEnd/app/frontend/src/App.css`, add:

```css
.dicom-viewer-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 320px 320px;
  gap: 6px;
  margin-top: 12px;
}

.dicom-viewport {
  background: #000;
  border-radius: 6px;
  overflow: hidden;
}
```

- [ ] **Step 4: Build and manually verify against a real CBCT export**

Run: `npm run build` (expect clean build — fix any import-name mismatches surfaced here first, per Step 0's note about confirming exact exports against the installed package version)

Then use the `run` skill: upload a real `.zip` of an actual CBCT study (or a small test CT series if no CBCT export is on hand) through the gallery page, open it from a client's CBCT tab, and confirm all four panes render something (even if orientation/quality isn't pixel-perfect yet — the bar for this task is "a real scan renders in all 4 panes," not "radiologist-grade correctness"). Note explicitly in your final report whether this real-browser check was performed, per this project's standing verification requirement for frontend work.

- [ ] **Step 5: Commit**

```bash
git add app/frontend/src/components/DicomViewer.jsx app/frontend/src/utils/dicomViewerSetup.js app/frontend/src/App.css
git commit -m "feat: add 4-pane Cornerstone3D DICOM viewer"
```

---

### Task 14: Copy the frontend build into the backend and do a final full-stack pass

Per this project's standing rule (`feedback_build_after_frontend_changes`): always rebuild and copy `dist/` into `Dental_Backend/public/app/` after frontend changes.

**Files:**
- No new files — deployment step only.

- [ ] **Step 1: Run the full backend suite one more time**

Run (from `Dental_Backend`): `php artisan test`
Expected: all tests pass (this project's whole suite, not just this feature's new tests)

- [ ] **Step 2: Run Pint**

Run: `./vendor/bin/pint --test app/Models/DicomStudy.php app/Models/DicomSeries.php app/Http/Controllers/Api/DicomStudyController.php app/Http/Requests/DicomStudy/ app/Http/Resources/DicomStudyResource.php app/Http/Resources/DicomSeriesResource.php app/Services/DicomTagReader.php`
Expected: passes, or run without `--test` to auto-fix if it reports issues

- [ ] **Step 3: Rebuild the frontend**

Run (from `Dental_FrontEnd/app/frontend`): `npm run build`

- [ ] **Step 4: Copy the build into the backend's public/app**

```bash
cp -r Dental_FrontEnd/app/frontend/dist/. Dental_Backend/public/app/
```

- [ ] **Step 5: Report status to the user**

Summarize: backend tests passing, frontend build clean, and explicitly state whether Task 10's and Task 13's real-browser verification steps were actually carried out — this project's standing instruction is to never claim a frontend feature works without either having clicked through it in a real browser or saying plainly that this wasn't done.

- [ ] **Step 6: Do not deploy to production yet**

This plan does not include a "push to production" step — confirm with the user before syncing/deploying this feature live, the same way every other production change this session has been confirmed first.

---

## What's deliberately not in this plan

- **Measurement tools** (distance/angle, Hounsfield density probe, implant/nerve-canal path) — Milestone 2 and 3, per the design spec's own phasing (§10). Do not add `@cornerstonejs/tools` annotation tools or the `dicom_measurements` table in this plan; write a fresh plan for that once this one has shipped and the team has hands-on Cornerstone3D experience from Task 13.
- **PACS/WADO/KOS live integration** — explicitly out of scope for the whole feature per the design spec §1/§2, not just this milestone.
- **The "browse all studies and attach one" mode** on `DicomStudyPicker` (X-ray's `XrayImagePicker` has a "browse studio" second mode) — v1 only shows this client's already-linked studies; add the browse mode later if asked for, following the exact pattern already in `XrayImagePicker.jsx`.
