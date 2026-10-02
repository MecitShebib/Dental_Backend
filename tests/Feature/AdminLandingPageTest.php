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

    public function test_edit_page_exposes_hub_and_every_specialty_in_all_three_locales(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/landing-page');

        $response->assertOk();
        $response->assertViewHas('hub', function ($hub) {
            return array_keys($hub) === ['en', 'ar', 'tr'];
        });
        $response->assertViewHas('specialtiesContent', function ($content) {
            return array_keys($content) === LandingPageContent::SPECIALTIES
                && array_keys($content['dental']) === ['en', 'ar', 'tr'];
        });
    }

    public function test_update_persists_nested_hub_and_specialty_content_including_contact_and_quote(): void
    {
        $admin = $this->adminUser();

        $payload = [
            'hub' => LandingPageContent::hubAll(),
            ...LandingPageContent::allSpecialtiesAll(),
        ];
        $payload['hub']['ar']['hero']['headline'] = 'عنوان رئيسي معدّل';
        $payload['dental']['tr']['contact']['headline'] = 'Güncellenmiş başlık';
        $payload['gynecology']['en']['quote']['submit_label'] = 'Get my quote';
        $payload['orthopedics']['en']['pricing'][1]['highlighted'] = '1';

        $response = $this->actingAs($admin)->put('/admin/landing-page', ['content' => $payload]);

        $response->assertRedirect(route('admin.landing-page.edit'));
        $response->assertSessionHas('status');

        $this->assertSame('عنوان رئيسي معدّل', LandingPageContent::hub('ar')['hero']['headline']);
        $this->assertSame('Güncellenmiş başlık', LandingPageContent::specialty('dental', 'tr')['contact']['headline']);
        $this->assertSame('Get my quote', LandingPageContent::specialty('gynecology', 'en')['quote']['submit_label']);
        $this->assertTrue(LandingPageContent::specialty('orthopedics', 'en')['pricing'][1]['highlighted']);
    }

    public function test_admin_can_switch_a_plan_off_everywhere(): void
    {
        $payload = [
            'hub' => LandingPageContent::hubAll(),
            ...LandingPageContent::allSpecialtiesAll(),
            'plans' => ['starter' => '0', 'essentials' => '1', 'professional' => '1', 'growth' => '1', 'enterprise' => '1'],
        ];

        $this->actingAs($this->adminUser())->put('/admin/landing-page', ['content' => $payload])
            ->assertRedirect(route('admin.landing-page.edit'));

        $this->assertFalse(LandingPageContent::planVisibility()['starter']);
        $this->assertTrue(LandingPageContent::planVisibility()['growth']);

        foreach (LandingPageContent::SPECIALTY_SLUGS as $slug) {
            $this->get("/{$slug}")->assertOk()->assertDontSee('For solo practitioners getting started.')->assertSee('With more seats for larger teams.');
            $this->get("/tr/{$slug}")->assertOk()->assertDontSee('Yeni başlayan bireysel hekimler için.');
        }

        $this->getJson('/api/public/plan-visibility')
            ->assertOk()
            ->assertJsonPath('plans.starter', false)
            ->assertJsonPath('plans.professional', true);
    }

    public function test_shared_plan_features_sit_above_the_cards_and_starter_and_essentials_list_only_seats(): void
    {
        foreach (array_keys(LandingPageContent::SPECIALTY_SLUGS) as $specialty) {
            foreach (['en', 'ar', 'tr'] as $locale) {
                $layout = LandingPageContent::pricingLayout(LandingPageContent::specialty($specialty, $locale)['pricing']);
                $tiers = collect($layout['tiers'])->keyBy('plan');

                $this->assertGreaterThanOrEqual(10, count($layout['shared']), "{$specialty}/{$locale}");
                $this->assertCount(2, preg_split('/\r?\n/', $tiers['starter']['features']), "{$specialty}/{$locale}");
                $this->assertCount(2, preg_split('/\r?\n/', $tiers['essentials']['features']), "{$specialty}/{$locale}");
                // Every card lists only its seats -- no "Everything in ...",
                // support or other extras.
                $this->assertCount(2, preg_split('/\r?\n/', $tiers['professional']['features']), "{$specialty}/{$locale}");
                $this->assertCount(2, preg_split('/\r?\n/', $tiers['growth']['features']), "{$specialty}/{$locale}");
                $this->assertCount(1, preg_split('/\r?\n/', $tiers['enterprise']['features']), "{$specialty}/{$locale}");
            }
        }

        $this->get('/dentavaria')->assertOk()
            ->assertSee('Included in every plan')
            ->assertSee('Conflict-free appointment scheduling')
            ->assertDontSee('Everything in')
            ->assertDontSee('Priority support')
            ->assertSee('For established clinics with a mid-sized team.');
        $this->get('/tr/dentavaria')->assertOk()->assertDontSee("Temel'deki her şey", false)->assertSee('Orta ölçekli ekibe sahip yerleşik klinikler için.');
        $this->get('/ar/dentavaria')->assertOk()->assertDontSee('كل ما في')->assertSee('للعيادات الراسخة ذات الفريق المتوسط.');
    }

    public function test_shared_plan_features_stay_above_the_cards_when_starter_is_hidden(): void
    {
        LandingPageContent::query()->create(['content' => [
            'plans' => ['starter' => false, 'essentials' => true, 'professional' => true, 'growth' => true, 'enterprise' => true],
        ]]);

        $layout = LandingPageContent::pricingLayout(LandingPageContent::specialty('dental', 'en')['pricing']);

        $this->assertContains('Conflict-free appointment scheduling', $layout['shared']);
        $this->assertNull(collect($layout['tiers'])->firstWhere('plan', 'starter'));
        $this->assertSame("Up to 3 doctors\nUp to 2 assistant users (non-doctor staff)", collect($layout['tiers'])->firstWhere('plan', 'essentials')['features']);
        $this->get('/dentavaria')->assertOk()->assertSee('Included in every plan')->assertDontSee('For solo practitioners getting started.');
    }

    public function test_hub_and_specialty_footers_show_the_company_contact_details(): void
    {
        config([
            'services.support.email' => 'info@doctovaria.com.tr',
            'services.support.phone' => '+905510882239',
            'services.support.whatsapp' => '+905510882239',
        ]);

        foreach (['/', '/tr', '/ar', '/dentavaria', '/tr/dietavaria', '/ar/gynevaria'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('mailto:info@doctovaria.com.tr', false)
                ->assertSee('tel:+905510882239', false)
                ->assertSee('https://wa.me/905510882239', false)
                ->assertSee('+90 551 088 22 39');
        }
    }

    public function test_footer_skips_contact_details_that_are_not_configured(): void
    {
        config(['services.support.phone' => null, 'services.support.whatsapp' => null]);

        $this->get('/dentavaria')->assertOk()->assertDontSee('tel:', false)->assertDontSee('https://wa.me/', false);
    }

    public function test_all_five_plans_are_visible_by_default(): void
    {
        $this->getJson('/api/public/plan-visibility')->assertOk()->assertExactJson(['plans' => [
            'starter' => true, 'essentials' => true, 'professional' => true, 'growth' => true, 'enterprise' => true,
        ]]);

        $this->get('/dentavaria')->assertOk()->assertSee('For solo practitioners getting started.')->assertSee('$140');
    }

    public function test_legacy_saved_pricing_rows_without_plan_keys_do_not_mask_the_new_tiers(): void
    {
        LandingPageContent::create(['content' => ['dental' => ['en' => ['pricing' => [
            ['name' => 'Essentials', 'price_monthly' => '$49', 'price_yearly' => '$39', 'highlighted' => false, 'features' => 'old'],
            ['name' => 'Growth', 'price_monthly' => '$249', 'price_yearly' => '$199', 'highlighted' => true, 'features' => 'old'],
            ['name' => 'Enterprise', 'price_monthly' => 'Custom', 'price_yearly' => 'Custom', 'highlighted' => false, 'features' => 'old'],
        ]]]]]);

        $pricing = LandingPageContent::specialty('dental', 'en')['pricing'];

        $this->assertSame(LandingPageContent::PLANS, array_column($pricing, 'plan'));
        $this->assertSame(['$30', '$75', '$140', '$220', 'Custom'], array_column($pricing, 'price_monthly'));
    }

    public function test_saved_pricing_rows_are_matched_by_plan_key(): void
    {
        LandingPageContent::create(['content' => ['dental' => ['en' => ['pricing' => [
            ['plan' => 'growth', 'price_monthly' => '$199'],
        ]]]]]);

        $pricing = collect(LandingPageContent::specialty('dental', 'en')['pricing'])->keyBy('plan');

        $this->assertCount(5, $pricing);
        $this->assertSame('$199', $pricing['growth']['price_monthly']);
        $this->assertSame('$140', $pricing['professional']['price_monthly']);
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
