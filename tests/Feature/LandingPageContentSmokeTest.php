<?php

namespace Tests\Feature;

use App\Models\LandingPageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageContentSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_and_every_specialty_resolve_in_every_locale(): void
    {
        foreach (LandingPageContent::LOCALES as $locale) {
            $hub = LandingPageContent::hub($locale);
            $this->assertCount(5, $hub['products'], "hub products count wrong for {$locale}");
            $this->assertNotEmpty($hub['hero']['headline']);
        }

        foreach (LandingPageContent::SPECIALTIES as $specialty) {
            foreach (LandingPageContent::LOCALES as $locale) {
                $content = LandingPageContent::specialty($specialty, $locale);
                $this->assertNotEmpty($content['hero']['headline'], "{$specialty}/{$locale} hero missing");
                $this->assertGreaterThanOrEqual(9, count($content['features']), "{$specialty}/{$locale} features too few");
                $this->assertCount(3, $content['pricing'], "{$specialty}/{$locale} pricing tiers wrong");
                $this->assertCount(3, $content['testimonials'], "{$specialty}/{$locale} testimonials wrong");
                $this->assertCount(6, $content['faq'], "{$specialty}/{$locale} faq wrong");
                $this->assertNotEmpty($content['footer']['contact_email']);
                $this->assertNotEmpty($content['contact']['submit_label']);
                $this->assertNotEmpty($content['quote']['submit_label']);
            }
        }
    }

    public function test_specialty_key_for_slug_resolves_all_five(): void
    {
        $this->assertSame('dental', LandingPageContent::specialtyKeyForSlug('dentavaria'));
        $this->assertSame('gynecology', LandingPageContent::specialtyKeyForSlug('gynevaria'));
        $this->assertSame('internal_medicine', LandingPageContent::specialtyKeyForSlug('medivaria'));
        $this->assertSame('orthopedics', LandingPageContent::specialtyKeyForSlug('orthovaria'));
        $this->assertSame('cosmetic', LandingPageContent::specialtyKeyForSlug('estevaria'));
        $this->assertNull(LandingPageContent::specialtyKeyForSlug('nonexistent'));
    }
}
