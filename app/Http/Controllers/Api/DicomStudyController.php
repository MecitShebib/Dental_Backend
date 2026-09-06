<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DicomStudy\StoreDicomStudyRequest;
use App\Http\Requests\DicomStudy\UpdateDicomStudyRequest;
use App\Http\Resources\DicomStudyResource;
use App\Models\Client;
use App\Models\DicomSeries;
use App\Models\DicomStudy;
use App\Services\DicomTagReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DicomStudyController extends Controller
{
    public function __construct(protected DicomTagReader $tagReader) {}

    public function index(Request $request)
    {
        $studies = $request->user()->company->dicomStudies()
            ->with(['client', 'series'])
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('client_id'))
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

        try {
            $data = $request->validated();
            $clientId = $this->resolveClientId($data['client_id'] ?? null);

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
                    Storage::disk('local')->putFileAs($storagePath, $tempPath, "{$index}.dcm");
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
        } finally {
            if ($extractDir !== null) {
                \Illuminate\Support\Facades\File::deleteDirectory($extractDir);
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
        return Storage::disk('local')->response("{$dicomSeries->storage_path}/{$index}.dcm");
    }

    /**
     * @return array<string, string> temp file path => original filename
     */
    protected function extractZip($archiveFile, string $extractDir): array
    {
        $zip = new ZipArchive();
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
