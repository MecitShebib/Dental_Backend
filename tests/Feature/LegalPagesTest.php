<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_legal_page_renders_in_every_locale_as_doctovaria(): void
    {
        foreach (['en', 'ar', 'tr'] as $locale) {
            foreach (['privacy-policy', 'terms-of-service'] as $page) {
                $this->get("/{$locale}/{$page}")
                    ->assertOk()
                    ->assertSee('— Doctovaria</title>', false)
                    ->assertSee('/brand/doctovaria_logo.png', false)
                    // Every {placeholder} must have been resolved.
                    ->assertDontSee('{entity}', false)
                    ->assertDontSee('{email}', false)
                    ->assertDontSee('{privacy_email}', false);
            }
        }
    }

    public function test_the_terms_contain_the_personal_account_clause(): void
    {
        $this->get('/tr/terms-of-service')->assertOk()->assertSee('Her hesap tek kişiye aittir');
        $this->get('/en/terms-of-service')->assertOk()->assertSee('One person, one account');
    }

    public function test_the_privacy_policy_prints_only_the_configured_identity_lines(): void
    {
        config([
            'services.legal.entity_name' => 'Örnek Sağlık Yazılım A.Ş.',
            'services.legal.entity_mersis' => '0123456789000015',
            'services.legal.entity_address' => null,
        ]);

        $this->get('/tr/privacy-policy')
            ->assertOk()
            ->assertSee('Örnek Sağlık Yazılım A.Ş.')
            ->assertSee('MERSİS no: 0123456789000015')
            ->assertDontSee('Adres:');
    }

    public function test_the_hub_landing_page_no_longer_links_to_api_documentation(): void
    {
        $this->get('/tr')->assertOk()->assertDontSee('/api-docs', false);
    }
}
