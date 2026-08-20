<?php

namespace Tests\Feature;

use App\Models\LandingPageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_specialty_page_renders_in_every_locale_with_its_own_brand_and_accent(): void
    {
        foreach (LandingPageContent::SPECIALTY_SLUGS as $key => $slug) {
            $response = $this->get("/{$slug}");
            $response->assertOk();
            $response->assertSee(LandingPageContent::specialty($key, 'en')['footer']['copyright_name']);
            $response->assertSee(LandingPageContent::SPECIALTY_ACCENTS[$key], false);

            foreach (['ar', 'tr'] as $locale) {
                $localized = $this->get("/{$locale}/{$slug}");
                $localized->assertOk();
                $localized->assertSee($locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"', false);
            }
        }
    }

    public function test_unknown_specialty_slug_404s(): void
    {
        $this->get('/not-a-real-product')->assertNotFound();
        $this->get('/en/not-a-real-product')->assertNotFound();
    }

    public function test_hub_page_lists_all_five_products_linking_to_their_own_pages(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Doctovaria');

        foreach (LandingPageContent::SPECIALTY_SLUGS as $slug) {
            $response->assertSee("/{$slug}", false);
        }
    }
}
