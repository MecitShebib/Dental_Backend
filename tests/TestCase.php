<?php

namespace Tests;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Signs the KVKK Açık Rıza Beyanı for $client so RequiresKvkkConsent
     * middleware lets tests through to AI endpoints (send-message,
     * transcribe, generate, confirm) across every specialty. Creates the
     * template on the fly rather than requiring KvkkConsentTemplateSeeder
     * to have run -- most Feature tests build their Company by hand, not
     * through Admin\CompanyController::store().
     */
    protected function signKvkkConsent(Client $client): void
    {
        $template = ConsentTemplate::query()->firstOrCreate(
            ['company_id' => $client->company_id, 'kind' => ConsentTemplate::KIND_KVKK_EXPLICIT_CONSENT],
            ['title' => 'Açık Rıza Beyanı', 'body' => 'Test consent body.', 'language' => 'tr', 'is_active' => true]
        );

        ClientConsent::create([
            'client_id' => $client->id,
            'consent_template_id' => $template->id,
            'title' => $template->title,
            'body' => $template->body,
            'signature_path' => 'consent-signatures/test-fixture.png',
            'signed_at' => now(),
        ]);
    }
}
