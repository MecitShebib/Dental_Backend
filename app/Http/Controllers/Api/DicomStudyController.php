<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DicomStudy\StoreDicomStudyRequest;
use App\Http\Requests\DicomStudy\UpdateDicomStudyRequest;
use App\Http\Resources\DicomStudyResource;
use App\Models\Client;
use App\Models\DicomSeries;
use App\Models\DicomStudy;
use App\Models\Specialty;
use App\Services\DicomFrameExtractor;
use App\Services\DicomTagReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DicomStudyController extends Controller
{
    public function __construct(
        protected DicomTagReader $tagReader,
        protected DicomFrameExtractor $frameExtractor,
    ) {}

    public function index(Request $request)
    {
        $actingUser = $request->user();
        $branchId = $actingUser->is_doctor && $actingUser->branch_id ? $actingUser->branch_id : $request->query('branch_id');
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $studies = $actingUser->company->dicomStudies()
            ->with(['client', 'series'])
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('client_id'))
            // A study with no branch_id/specialty_id assigned yet (pre-dates
            // this scoping) stays visible from every branch/specialty rather
            // than silently disappearing.
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->when($specialtyId, fn ($q) => $q->where(fn ($q2) => $q2->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->latest()
            ->get();

        return $this->success(DicomStudyResource::collection($studies));
    }

    public function show(DicomStudy $dicomStudy)
    {
        return $this->success(DicomStudyResource::make($dicomStudy->load(['client', 'series'])));
    }

    public function store(StoreDicomStudyRequest $request)
    {
        $extractDir = null;

        // A real CBCT/CT export is routinely 100-500MB. Writing that much
        // (read the temp upload, write the destination copy -- double I/O)
        // on shared hosting disk can genuinely take longer than PHP's
        // default 30s max_execution_time. public/.user.ini raises this to
        // 300s, but has already proven unreliable on this host for another
        // endpoint (the download side's memory_limit override was silently
        // ignored) -- set_time_limit() is a runtime override that doesn't
        // depend on .user.ini/php.ini actually being picked up, so this
        // stays effective regardless.
        set_time_limit(300);

        try {
            $actingUser = $request->user();
            $data = $request->validated();
            $clientId = $this->resolveClientId($data['client_id'] ?? null);

            // Same rule as everywhere else: a user with their own branch_id/
            // specialty_id (doctor) always uploads into their own scope,
            // overriding whatever the request sent.
            $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);
            $specialtyId = $actingUser->is_doctor
                ? $actingUser->specialty_id
                : ($data['specialty_id'] ?? null);

            if (isset($data['archive'])) {
                $extractDir = storage_path('app/dicom-uploads/'.uniqid());
                $dicomFilePaths = $this->extractZip($request->file('archive'), $extractDir);
            } else {
                $dicomFilePaths = $this->saveLooseFiles($request->file('files'));
            }

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
                'branch_id' => $branchId,
                'specialty_id' => $specialtyId,
                'uploaded_by' => $request->user()->id,
                'modality' => reset($bySeriesUid)['tags']['modality'] ?? null,
                'study_date' => reset($bySeriesUid)['tags']['study_date'] ?? null,
                'description' => reset($bySeriesUid)['tags']['study_description'] ?? null,
                'slice_count' => array_sum(array_map(fn ($series) => count($series['files']), $bySeriesUid)),
                'status' => 'ready',
            ]);

            foreach ($bySeriesUid as $seriesUid => $series) {
                // The directory name is a freshly generated UUID, never the
                // series_uid tag itself -- that value comes straight out of
                // attacker-controlled file bytes (DicomTagReader applies no
                // character/format validation to it) and this path is later
                // fed to putFileAs()/deleteDirectory() unmodified. A crafted
                // tag like "../../xray-images" would otherwise let Flysystem's
                // normalizer walk the resulting path out of this study's own
                // directory entirely (two real path segments precede it, so
                // both ".." pops succeed silently instead of throwing), then
                // have deleteDirectory() on destroy() wipe an unrelated,
                // cross-tenant directory on the shared 'local' disk. The raw
                // tag value is still kept in the series_uid *column* below
                // (a plain DB value, never touched by any Storage:: call) for
                // display/matching -- only its use as a filesystem path is
                // the problem.
                $storagePath = 'dicom-studies/'.$study->uuid.'/'.(string) Str::uuid();

                foreach ($series['files'] as $index => $tempPath) {
                    // Private disk (KVKK): raw DICOM files carry the same class of
                    // sensitive health data as X-ray images and consent
                    // signatures, both of which already live on this disk rather
                    // than the public one -- see the 'local' disk comment in
                    // config/filesystems.php and the routes/api.php note above
                    // the xray-images.file/client-consents.signature/
                    // expenses.attachment signed-route group. A signed
                    // file-streaming route for series slices, mirroring
                    // XrayImageController::file(), is expected to land in a
                    // later task.
                    $sourceSize = filesize($tempPath);
                    Storage::disk('local')->putFileAs($storagePath, $tempPath, "{$index}.dcm");

                    // putFileAs() returns false on failure but nothing here
                    // threw, so a copy that failed partway (or didn't start
                    // at all) would otherwise leave a DicomSeries row
                    // pointing at a file that was never really written --
                    // exactly what happened in production (confirmed via
                    // the log: uploads reported success, then every attempt
                    // to open that scan later 500'd on a plain
                    // "No such file or directory"). Comparing sizes instead
                    // of a plain exists() check also catches a copy that
                    // started but was cut short.
                    $writtenSize = Storage::disk('local')->exists("{$storagePath}/{$index}.dcm")
                        ? Storage::disk('local')->size("{$storagePath}/{$index}.dcm")
                        : null;

                    if ($writtenSize !== $sourceSize) {
                        Storage::disk('local')->deleteDirectory("dicom-studies/{$study->uuid}");
                        // forceDelete, not delete -- same reasoning as destroy()
                        // below: this model is SoftDeletes, and a soft-deleted
                        // row would leave its dicom_series rows behind (the FK
                        // cascade only fires on a real delete).
                        $study->forceDelete();

                        Log::error('DICOM file failed to save completely during upload', [
                            'study_uuid' => $study->uuid,
                            'series_uid' => $seriesUid,
                            'index' => $index,
                            'source_size' => $sourceSize,
                            'written_size' => $writtenSize,
                        ]);

                        throw ValidationException::withMessages([
                            'files' => ['One or more files failed to upload completely. Please try again.'],
                        ]);
                    }
                }

                $tags = $series['tags'];

                // A real CBCT/CT export is almost always uploaded as ONE
                // file holding hundreds of frames, not one file per slice --
                // only that single-file case can use fixed byte-offset math
                // to serve one frame at a time (see DicomFrameExtractor);
                // compressed/encapsulated PixelData (pixel_data_length ===
                // null) has no fixed per-frame offset without parsing the
                // Basic Offset Table, which this reader doesn't do.
                $isFrameExtractable = count($series['files']) === 1
                    && ($tags['frame_count'] ?? 1) > 1
                    && ($tags['pixel_data_length'] ?? null) !== null;

                $study->series()->create([
                    'series_uid' => $seriesUid,
                    'rows' => $tags['rows'] ?? null,
                    'columns' => $tags['columns'] ?? null,
                    'slice_count' => count($series['files']),
                    'frame_count' => $tags['frame_count'] ?? 1,
                    'bits_allocated' => $tags['bits_allocated'] ?? null,
                    'bits_stored' => $tags['bits_stored'] ?? null,
                    'high_bit' => $tags['high_bit'] ?? null,
                    'pixel_representation' => $tags['pixel_representation'] ?? null,
                    'samples_per_pixel' => $tags['samples_per_pixel'] ?? null,
                    'photometric_interpretation' => $tags['photometric_interpretation'] ?? null,
                    'sop_class_uid' => $tags['sop_class_uid'] ?? null,
                    'transfer_syntax_uid' => $tags['transfer_syntax_uid'] ?? null,
                    'pixel_data_offset' => $tags['pixel_data_offset'] ?? null,
                    'is_frame_extractable' => $isFrameExtractable,
                    'pixel_spacing_x' => $tags['pixel_spacing_x'] ?? null,
                    'pixel_spacing_y' => $tags['pixel_spacing_y'] ?? null,
                    'slice_thickness' => $tags['slice_thickness'] ?? null,
                    'orientation' => $tags['orientation'] ?? null,
                    'storage_path' => $storagePath,
                ]);
            }

            return $this->success($study->load('series'), 'Study uploaded successfully.', 201);
        } finally {
            if ($extractDir !== null) {
                File::deleteDirectory($extractDir);
            }
        }
    }

    public function update(UpdateDicomStudyRequest $request, DicomStudy $dicomStudy)
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $data['client_id'] = $this->resolveClientId($data['client_id']);
        }

        $dicomStudy->update($data);

        return $this->success(DicomStudyResource::make($dicomStudy->fresh(['client', 'series'])), 'Study updated successfully.');
    }

    public function destroy(DicomStudy $dicomStudy)
    {
        foreach ($dicomStudy->series as $series) {
            Storage::disk('local')->deleteDirectory($series->storage_path);
        }

        $dicomStudy->forceDelete();

        return $this->success(null, 'Study deleted successfully.');
    }

    /**
     * Streams one slice file for the Cornerstone3D viewer. No auth:sanctum
     * on this route (a wadouri: image loader's XHR can't carry a bearer
     * token) -- access control is the `signed` middleware alone, matching
     * XrayImageController::file(); the signed URL is only ever minted by
     * DicomSeriesResource for an already-authenticated, tenant-scoped
     * request.
     */
    public function seriesFile(DicomSeries $dicomSeries, int $index)
    {
        $disk = Storage::disk('local');
        $path = "{$dicomSeries->storage_path}/{$index}.dcm";

        // Missing file -> a clean 404 instead of Storage::response()'s own
        // uncaught exception from its eager mimeType()/size() lookups
        // (Flysystem's UnableToRetrieveMetadata), which used to surface to
        // the frontend as an opaque "HTTP 500" with nothing logged to
        // explain why.
        if (! $disk->exists($path)) {
            Log::warning('DICOM series file missing on disk', [
                'dicom_series_id' => $dicomSeries->id,
                'index' => $index,
                'path' => $path,
            ]);

            abort(404, 'This scan slice could not be found.');
        }

        // A real CBCT/DICOM export is routinely 100-500MB. Storage::response()
        // streams via fpassthru(), which is memory-safe on PHP's own side --
        // but the production log showed "Allowed memory size of 134217728
        // [128M] bytes exhausted (tried to allocate 305008640 bytes)", i.e.
        // an allocation attempt for almost exactly this file's full size.
        // That number only makes sense if something *outside* PHP's control
        // here (this shared host's Apache/PHP-FPM output buffering, or
        // gzip/deflate compression needing the whole body before it can
        // compress) buffers fpassthru()'s output in full before it reaches
        // the client, regardless of memory_limit -- raising memory_limit
        // (already tried via public/.user.ini) doesn't reach that layer at
        // all. Reading and echoing fixed-size chunks with an explicit
        // flush() after each one forces that buffer back out continuously,
        // so it never has the chance to grow anywhere near the file's full
        // size no matter what's buffering it.
        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            while (! feof($stream)) {
                echo fread($stream, 1024 * 1024);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/dicom',
            'Content-Length' => (string) $disk->size($path),
            'Content-Disposition' => 'inline; filename="'.$index.'.dcm"',
            // Tells an nginx layer in front of PHP-FPM (common on cPanel/
            // LiteSpeed hosts) not to buffer this response either -- the
            // one piece of the puzzle the flush() calls above can't reach.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Streams ONE frame of a multi-frame series' file, built on the fly as
     * its own small, standalone DICOM file (see DicomFrameExtractor) --
     * instead of the viewer downloading the whole multi-hundred-MB export
     * just to decode a single frame from it. Same signed-URL access control
     * as seriesFile() above.
     */
    public function seriesFrame(DicomSeries $dicomSeries, int $frame)
    {
        if (! $dicomSeries->is_frame_extractable) {
            abort(404, 'This scan does not support per-frame streaming.');
        }

        if ($frame < 0 || $frame >= $dicomSeries->frame_count) {
            abort(404, 'This frame is out of range for this scan.');
        }

        try {
            $bytes = $this->frameExtractor->extractFrame($dicomSeries, $frame);
        } catch (\Throwable $e) {
            Log::error('DICOM frame extraction failed', [
                'dicom_series_id' => $dicomSeries->id,
                'frame' => $frame,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to extract this frame.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/dicom',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline; filename="frame-'.$frame.'.dcm"',
        ]);
    }

    /**
     * @return array<string, string> temp file path => original filename
     */
    protected function extractZip($archiveFile, string $extractDir): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archiveFile->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'archive' => ['Failed to read the zip file. Please verify it is not corrupted.'],
            ]);
        }

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
