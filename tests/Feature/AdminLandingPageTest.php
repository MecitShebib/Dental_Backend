<?php

namespace Tests\Feature;

use App\Enums\InquiryType;
use App\Models\LandingPageContent;
use App\Models\LandingPageInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'is_project_admin' => true,
            'status' => 'active',
        ]);
    }

    public function test_edit_page_exposes_all_three_locales(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/landing-page');

        $response->assertOk();
        $response->assertViewHas('content', function ($content) {
            return array_keys($content) === ['en', 'ar', 'tr'];
        });
    }

    public function test_update_persists_nested_per_locale_content_including_contact_and_quote(): void
    {
        $admin = $this->adminUser();

        $payload = LandingPageContent::currentAll();
        $payload['ar']['hero']['headline'] = 'عنوان رئيسي معدّل';
        $payload['tr']['contact']['headline'] = 'Güncellenmiş başlık';
        $payload['en']['quote']['submit_label'] = 'Get my quote';
        $payload['en']['pricing'][1]['highlighted'] = '1';

        $response = $this->actingAs($admin)->put('/admin/landing-page', ['content' => $payload]);

        $response->assertRedirect(route('admin.landing-page.edit'));
        $response->assertSessionHas('status');

        $this->assertSame('عنوان رئيسي معدّل', LandingPageContent::current('ar')['hero']['headline']);
        $this->assertSame('Güncellenmiş başlık', LandingPageContent::current('tr')['contact']['headline']);
        $this->assertSame('Get my quote', LandingPageContent::current('en')['quote']['submit_label']);
        $this->assertTrue(LandingPageContent::current('en')['pricing'][1]['highlighted']);
    }

    public function test_guest_cannot_access_landing_page_editor(): void
    {
        $response = $this->get('/admin/landing-page');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_project_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/landing-page');

        $response->assertForbidden();
    }

    public function test_admin_can_list_mark_read_and_delete_inquiries(): void
    {
        $admin = $this->adminUser();

        $unread = LandingPageInquiry::create([
            'type' => InquiryType::Contact,
            'locale' => 'en',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello there',
        ]);

        $index = $this->actingAs($admin)->get('/admin/inquiries');
        $index->assertOk();
        $index->assertSee('Jane Doe');

        $markRead = $this->actingAs($admin)->patch("/admin/inquiries/{$unread->id}/read");
        $markRead->assertRedirect();
        $this->assertNotNull($unread->fresh()->read_at);

        $destroy = $this->actingAs($admin)->delete("/admin/inquiries/{$unread->id}");
        $destroy->assertRedirect();
        $this->assertDatabaseMissing('landing_page_inquiries', ['id' => $unread->id]);
    }
}
