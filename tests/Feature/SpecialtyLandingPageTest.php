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

    public function test_the_five_2026_09_27_specialties_have_their_own_pages(): void
    {
        foreach (['pediavaria' => 'Pediavaria', 'physiovaria' => 'Physiovaria', 'hemavaria' => 'Hemavaria', 'surgivaria' => 'Surgivaria', 'genervaria' => 'Genervaria'] as $slug => $brand) {
            foreach (['en', 'ar', 'tr'] as $locale) {
                $this->get("/{$locale}/{$slug}")->assertOk()->assertSee($brand);
            }
            $this->get('/')->assertSee("/{$slug}", false);
        }
    }

    /**
     * Production's hub content was saved from Admin > Landing Page back when
     * there were six products (keyless rows, since `key` was never validated
     * and got dropped) with the old "six specialties" eyebrow. Newly added
     * products must still appear, and untouched old default text must not
     * mask the updated default -- while admin-edited text is kept.
     */
    public function test_hub_saved_before_new_specialties_still_lists_every_product(): void
    {
        $savedProducts = collect(LandingPageContent::hub('en')['products'])->take(6)
            ->map(fn (array $product) => ['name' => $product['name'], 'tagline' => $product['tagline'], 'body' => $product['body']])
            ->values()->all();
        $savedProducts[0]['body'] = 'Admin-edited dental blurb.';

        LandingPageContent::query()->create(['content' => ['hub' => ['en' => [
            'hero' => ['eyebrow' => 'One platform, six clinical specialties', 'headline' => 'Custom headline'],
            'products' => $savedProducts,
        ]]]]);

        $hub = LandingPageContent::hub('en');

        $this->assertCount(count(LandingPageContent::SPECIALTIES), $hub['products']);
        $this->assertSame('Admin-edited dental blurb.', $hub['products'][0]['body']);
        $this->assertSame('dental', $hub['products'][0]['key']);
        $this->assertContains('Pediavaria', array_column($hub['products'], 'name'));
        $this->assertSame('One platform, eleven clinical specialties', $hub['hero']['eyebrow']);
        $this->assertSame('Custom headline', $hub['hero']['headline']);

        $this->get('/')->assertOk()->assertSee('/pediavaria', false)->assertSee('Genervaria');
    }

    /**
     * Pedivaria/Generavaria were renamed to Pediavaria/Genervaria the same
     * day they went live -- keep the already-published URLs working.
     */
    public function test_pre_rename_brand_urls_redirect_permanently(): void
    {
        $this->get('/pedivaria')->assertStatus(301)->assertRedirect('/pediavaria');
        $this->get('/tr/generavaria')->assertStatus(301)->assertRedirect('/tr/genervaria');
        $this->get('/proposal-pedivaria.html')->assertStatus(301)->assertRedirect('/proposal-pediavaria.html');
        $this->get('/pitch-generavaria.html')->assertStatus(301)->assertRedirect('/pitch-genervaria.html');
    }

    public function test_unknown_specialty_slug_404s(): void
    {
        $this->get('/not-a-real-product')->assertNotFound();
        $this->get('/en/not-a-real-product')->assertNotFound();
    }

    public function test_hub_page_lists_every_product_linking_to_its_own_page(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Doctovaria');

        foreach (LandingPageContent::SPECIALTY_SLUGS as $slug) {
            $response->assertSee("/{$slug}", false);
        }
    }
}
