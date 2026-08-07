<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\XrayImage\StoreXrayImageRequest;
use App\Http\Requests\XrayImage\UpdateXrayImageRequest;
use App\Http\Resources\XrayImageResource;
use App\Models\Client;
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
        $images = $request->user()->company->xrayImages()
            ->with('client')
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('client_id'))
            ->latest()
            ->paginate($request->has('per_page') ? (int) $request->query('per_page') : null);

        $resource = XrayImageResource::collection($images);

        return $this->success($request->has('per_page') ? $resource->response()->getData(true) : $resource);
    }

    public function store(StoreXrayImageRequest $request)
    {
        $data = $request->validated();
        $clientId = $this->resolveClientId($data['client_id'] ?? null);

        $images = collect($request->file('images'))->map(function ($file) use ($request, $data, $clientId) {
            return $request->user()->company->xrayImages()->create([
                'client_id' => $clientId,
                'image_path' => $file->store('xray-images', 'public'),
                'original_filename' => $file->getClientOriginalName(),
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        });

        return $this->success(XrayImageResource::collection($images), 'Image(s) uploaded successfully.', 201);
    }

    public function update(UpdateXrayImageRequest $request, XrayImage $xrayImage)
    {
        $data = $request->validated();

        if (array_key_exists('client_id', $data)) {
            $data['client_id'] = $this->resolveClientId($data['client_id']);
        }

        $xrayImage->update($data);

        return $this->success(XrayImageResource::make($xrayImage->fresh('client')), 'Image updated successfully.');
    }

    public function destroy(XrayImage $xrayImage)
    {
        Storage::disk('public')->delete($xrayImage->image_path);
        $xrayImage->delete();

        return $this->success(null, 'Image deleted successfully.');
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
