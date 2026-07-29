<?php

namespace Tests\Feature;

use App\Enums\InquiryType;
use App\Models\LandingPageInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_an_inquiry(): void
    {
        $response = $this->from('/')->post('/contact', [
            'name' => 'Elif Demir',
            'email' => 'elif@example.com',
            'message' => 'Merhaba, fiyat bilgisi almak istiyorum.',
            'locale' => 'tr',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('inquiry_success', 'contact');

        $this->assertDatabaseHas('landing_page_inquiries', [
            'type' => InquiryType::Contact->value,
            'locale' => 'tr',
            'name' => 'Elif Demir',
            'email' => 'elif@example.com',
        ]);
    }

    public function test_contact_form_requires_name_email_and_message(): void
    {
        $response = $this->from('/')->post('/contact', []);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertSame(0, LandingPageInquiry::count());
    }

    public function test_quote_form_stores_an_inquiry_with_arabic_content(): void
    {
        $response = $this->from('/ar')->post('/quote', [
            'name' => 'عميل تجريبي',
            'email' => 'quote@example.com',
            'phone' => '+905551234567',
            'company' => 'عيادة تجريبية',
            'message' => 'نحتاج عرض سعر لعيادتنا',
            'locale' => 'ar',
        ]);

        $response->assertRedirect('/ar');
        $response->assertSessionHas('inquiry_success', 'quote');

        $inquiry = LandingPageInquiry::where('email', 'quote@example.com')->first();

        $this->assertNotNull($inquiry);
        $this->assertSame(InquiryType::Quote, $inquiry->type);
        $this->assertSame('عميل تجريبي', $inquiry->name);
        $this->assertSame('عيادة تجريبية', $inquiry->company);
        $this->assertNull($inquiry->read_at);
    }

    public function test_quote_form_allows_optional_phone_company_and_message(): void
    {
        $response = $this->post('/quote', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('landing_page_inquiries', [
            'type' => InquiryType::Quote->value,
            'email' => 'jane@example.com',
        ]);
    }
}
