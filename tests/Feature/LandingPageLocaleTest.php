<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_renders_english(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('The clinical operating system for modern dental practices.');
    }

    public function test_arabic_locale_renders_rtl_with_arabic_copy(): void
    {
        $response = $this->get('/ar');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('نظام التشغيل السريري لعيادات الأسنان الحديثة.');
    }

    public function test_turkish_locale_renders_turkish_copy(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('Modern diş kliniklerinin klinik işletim sistemi.');
    }

    public function test_invalid_locale_segment_falls_through_to_other_routes(): void
    {
        $response = $this->get('/xx');

        $response->assertNotFound();
    }
}
