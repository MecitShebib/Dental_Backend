<?php

namespace Tests\Feature;

use App\Models\LandingPageContent;
use App\Support\ApiDocumentation;
use Tests\TestCase;

class ApiDocsPageTest extends TestCase
{
    public function test_every_specialty_has_its_own_api_docs_page_with_its_own_brand(): void
    {
        $wordmarks = [
            'dental' => 'dentavaria_logo.png',
            'gynecology' => 'gynevaria_logo.png',
            'internal_medicine' => 'medivaria_logo.png',
            'orthopedics' => 'orthovaria_logo.png',
            'cosmetic' => 'estevaria_logo.png',
            'nutrition' => 'dietavaria_logo.png',
            'pediatrics' => 'pediavaria_logo.png',
            'physiotherapy' => 'physiovaria_logo.png',
            'hematology' => 'hemavaria_logo.png',
            'general_surgery' => 'surgivaria_logo.png',
            'general_practice' => 'genervaria_logo.png',
        ];

        foreach (LandingPageContent::SPECIALTIES as $specialty) {
            $response = $this->get("/{$specialty}/api-docs");

            $response->assertOk();
            $response->assertSee(ApiDocumentation::specialtyBrandName($specialty));
            $response->assertSee(LandingPageContent::SPECIALTY_ACCENTS[$specialty], false);
            $response->assertSee('/brand/'.$wordmarks[$specialty], false);
        }
    }

    public function test_theme_syncs_with_the_landing_page_via_the_shared_localstorage_key(): void
    {
        $response = $this->get('/dental/api-docs');
        $response->assertSee("localStorage.getItem('doctovaria-theme')", false);
        $response->assertDontSee('dentavaria-theme', false);
    }

    public function test_dental_specific_groups_only_appear_on_the_dental_page(): void
    {
        $dental = $this->get('/dental/api-docs');
        $dental->assertOk();
        $dental->assertSee('CBCT / DICOM Scans');
        $dental->assertSee('X-Ray Images');
        $dental->assertSee('Dental Lab');

        $gynecology = $this->get('/gynecology/api-docs');
        $gynecology->assertOk();
        $gynecology->assertDontSee('CBCT / DICOM Scans');
        $gynecology->assertDontSee('X-Ray Images');
        $gynecology->assertDontSee('Dental Lab');
    }

    public function test_shared_groups_appear_on_every_specialty_page(): void
    {
        foreach (LandingPageContent::SPECIALTIES as $specialty) {
            $response = $this->get("/{$specialty}/api-docs");
            $response->assertSee('Authentication');
            $response->assertSee('Appointments');
        }
    }

    public function test_unknown_specialty_404s(): void
    {
        $this->get('/not-a-real-specialty/api-docs')->assertNotFound();
    }

    public function test_old_unified_url_redirects_to_the_dental_docs(): void
    {
        $response = $this->get('/api-docs');
        $response->assertRedirect('/dental/api-docs');
    }
}
