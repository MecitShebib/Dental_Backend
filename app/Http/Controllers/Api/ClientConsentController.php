<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\StoreClientConsentRequest;
use App\Http\Resources\ClientConsentResource;
use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use App\Models\Visit;
use App\Services\ConsentService;
use Illuminate\Support\Facades\Storage;

class ClientConsentController extends Controller
{
    public function index(Client $client)
    {
        return $this->success(ClientConsentResource::collection(
            $client->consents()->latest('signed_at')->get()
        ));
    }

    public function store(StoreClientConsentRequest $request, Client $client, ConsentService $consents)
    {
        $data = $request->validated();
        $template = ConsentTemplate::query()->findOrFail($data['consent_template_id']);
        $visit = isset($data['visit_id']) ? Visit::query()->find($data['visit_id']) : null;

        $consent = $consents->sign($client, $template, $data['signature'], $visit, $request->user(), $request->ip());

        return $this->success(ClientConsentResource::make($consent), 'Consent signed successfully.', 201);
    }

    /**
     * Streams the signature image from the private disk. See
     * XrayImageController::file() for why this route has no bearer-token
     * check -- the signed URL itself is the authorization.
     */
    public function signature(ClientConsent $consent)
    {
        return Storage::disk('local')->response($consent->signature_path);
    }
}
