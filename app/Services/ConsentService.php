<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsentService
{
    /**
     * @param  string  $signatureDataUrl  a "data:image/png;base64,...." string from a <canvas> signature pad
     */
    public function sign(
        Client $client,
        ConsentTemplate $template,
        string $signatureDataUrl,
        ?Visit $visit,
        ?User $signedBy,
        ?string $ip,
    ): ClientConsent {
        $path = $this->storeSignature($signatureDataUrl);

        $sections = collect($template->sections ?? [])
            ->map(fn (array $section) => [
                'heading' => $this->render($section['heading'] ?? '', $client),
                'body' => $this->render($section['body'] ?? '', $client),
            ])
            ->values()
            ->all();

        return ClientConsent::create([
            'client_id' => $client->id,
            'consent_template_id' => $template->id,
            'visit_id' => $visit?->id,
            'title' => $template->title,
            'body' => $this->render($template->body, $client),
            'sections' => $sections ?: null,
            'signature_path' => $path,
            'signed_at' => now(),
            'ip_address' => $ip,
            'created_by' => $signedBy?->id,
        ]);
    }

    protected function render(string $body, Client $client): string
    {
        return strtr($body, [
            '{client_name}' => $client->name,
            '{company_name}' => $client->company?->name ?? '',
            '{date}' => now()->format('d/m/Y'),
        ]);
    }

    protected function storeSignature(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'signature' => ['The signature must be a base64-encoded PNG or JPEG image.'],
            ]);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'signature' => ['The signature image could not be decoded.'],
            ]);
        }

        $path = 'consent-signatures/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
