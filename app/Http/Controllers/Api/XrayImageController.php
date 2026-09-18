<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\XrayImage\StoreXrayImageRequest;
use App\Http\Requests\XrayImage\UpdateXrayImageRequest;
use App\Http\Resources\XrayImageResource;
use App\Jobs\AnalyzeXrayImageJob;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\XrayImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class XrayImageController extends Controller
{
    /**
     * The shared company-wide gallery. Optionally scoped to one client (used
     * both by the picker modal's "already linked to this patient" filter and
     * by ClientDetailsPage's own X-ray tab) or to unlinked-only (the
     * picker's default view of "images waiting to be filed").
     */
    public function index(Request $request)
    {
        $actingUser = $request->user();
        $branchId = $actingUser->is_doctor && $actingUser->branch_id ? $actingUser->branch_id : $request->query('branch_id');
        $specialtyId = $request->filled('specialty')
            ? Specialty::query()->where('key', $request->string('specialty')->value())->value('id')
            : null;

        $images = $actingUser->company->xrayImages()
            ->with('client')
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('client_id'))
            // An image with no branch_id/specialty_id assigned yet (pre-dates
            // this scoping) stays visible from every branch/specialty rather
            // than silently disappearing.
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->when($specialtyId, fn ($q) => $q->where(fn ($q2) => $q2->where('specialty_id', $specialtyId)->orWhereNull('specialty_id')))
            ->latest()
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = XrayImageResource::collection($images);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreXrayImageRequest $request)
    {
        $actingUser = $request->user();
        $data = $request->validated();
        $clientId = $this->resolveClientId($data['client_id'] ?? null);

        // Same rule as everywhere else: a user with their own branch_id/
        // specialty_id (doctor) always uploads into their own scope,
        // overriding whatever the request sent. Otherwise falls back to
        // whatever the request/frontend (its currently active branch/
        // specialty) explicitly provided.
        $branchId = $actingUser->branch_id ?: ($data['branch_id'] ?? null);
        $specialtyId = $actingUser->is_doctor
            ? $actingUser->specialty_id
            : ($data['specialty_id'] ?? null);

        $images = collect($request->file('images'))->map(function ($file) use ($request, $data, $clientId, $branchId, $specialtyId) {
            $image = $request->user()->company->xrayImages()->create([
                'client_id' => $clientId,
                'branch_id' => $branchId,
                'specialty_id' => $specialtyId,
                'image_path' => $file->store('xray-images', 'local'),
                'original_filename' => $file->getClientOriginalName(),
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);

            // Linked at upload time (rather than via the picker modal's later
            // "Save" action, see update() below) -- kick off the AI reading
            // right away instead of leaving it stuck unanalyzed forever.
            // dispatchSync(), not dispatch(): this host has no queue worker
            // process, so anything sent through ::dispatch() sits in the
            // `jobs` table untouched forever (confirmed via production
            // diagnostics -- pending AnalyzeXrayImageJob rows with 0 attempts).
            // Gated behind the same flag as the 3D Odontogram tab (see
            // config/features.php) -- this analysis exists only to feed that
            // tab, so there is no point spending AI tokens on it while the
            // tab itself is hidden.
            if ($clientId && config('features.three_d_odontogram')) {
                AnalyzeXrayImageJob::dispatchSync($image);
            }

            return $image;
        });

        return $this->success(XrayImageResource::collection($images), 'Image(s) uploaded successfully.', 201);
    }

    public function update(UpdateXrayImageRequest $request, XrayImage $xrayImage)
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $data['client_id'] = $this->resolveClientId($data['client_id']);
        }

        // Only the first time an image is linked to any patient -- editing
        // notes or re-linking an already-analyzed image shouldn't burn
        // another AI call, the reading is about the image content, not
        // which patient it's currently filed under.
        $shouldAnalyze = is_null($xrayImage->ai_analyzed_at);

        $xrayImage->update($data);

        if ($xrayImage->client_id && $shouldAnalyze && config('features.three_d_odontogram')) {
            AnalyzeXrayImageJob::dispatchSync($xrayImage);
        }

        return $this->success(XrayImageResource::make($xrayImage->fresh('client')), 'Image updated successfully.');
    }

    public function destroy(XrayImage $xrayImage)
    {
        Storage::disk('local')->delete($xrayImage->image_path);
        $xrayImage->delete();

        return $this->success(null, 'Image deleted successfully.');
    }

    /**
     * Streams the raw image from the private disk. Reached only via a
     * signed URL (see XrayImageResource::image_url and the `signed`
     * middleware on the xray-images.file route) -- there is no bearer-token
     * check here because the browser's plain <img> tag can't send one; the
     * signature itself, minted only for a request that already passed
     * auth:sanctum + tenant scoping, is what authorizes this.
     */
    public function file(XrayImage $xrayImage)
    {
        return Storage::disk('local')->response($xrayImage->image_path, $xrayImage->original_filename);
    }

    /**
     * The most recently AI-analyzed X-ray on file for this client -- the
     * "before" baseline the 3D odontogram tab renders (see
     * PatientOdontogram3D.jsx on the frontend, which merges this with the
     * client's own visit/appointment charts). Null fields mean no X-ray has
     * been analyzed yet (either none linked, or the analysis job hasn't run
     * or has failed) -- the frontend already handles that gracefully.
     */
    public function latestOdontogram(Client $client)
    {
        $xrayImage = $client->xrayImages()
            ->whereNotNull('ai_odontogram_status')
            ->latest('ai_analyzed_at')
            ->first();

        return $this->success([
            'odontogram_v2_status' => $xrayImage?->ai_odontogram_status,
            'analyzed_at' => $xrayImage?->ai_analyzed_at,
            'xray_image_uuid' => $xrayImage?->uuid,
        ]);
    }

    /**
     * The FormRequest's `exists:clients,id` check runs against the raw
     * query builder and does not see Client's BelongsToCompany scope, so a
     * client_id from another company would otherwise pass validation. Re-
     * resolving through the scoped model here is what actually enforces
     * tenant isolation, mirroring LabCaseController::resolveAppointment().
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
