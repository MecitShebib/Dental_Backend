<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\Specialty;
use App\Models\User;
use App\Services\SystemMessageService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['company_id' => null, 'is_project_admin' => true, 'status' => 'active']);
    }

    private function titles(Company $company): array
    {
        return CustomMessage::query()
            ->where('company_id', $company->id)
            ->where('system_key', 'patient_recall')
            ->orderBy('language')
            ->pluck('title', 'language')
            ->all();
    }

    public function test_creating_a_company_requires_and_stores_its_language(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/admin/companies', ['name' => 'No Lang', 'code' => 'NL-1', 'status' => 'active', 'currency' => 'TRY'])
            ->assertSessionHasErrors(['language'], null, 'default');

        $this->actingAs($admin)
            ->post('/admin/companies', ['name' => 'Arabic Clinic', 'code' => 'AR-1', 'status' => 'active', 'currency' => 'TRY', 'language' => 'ar'])
            ->assertRedirect(route('admin.companies.index'));

        $this->assertSame('ar', Company::query()->where('code', 'AR-1')->firstOrFail()->language->value);
    }

    public function test_system_message_titles_are_written_in_the_company_language(): void
    {
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();

        $arabic = Company::factory()->create(['language' => 'ar']);
        app(SystemMessageService::class)->seedForCompanySpecialty($arabic, $dental);
        $this->assertSame([
            'ar' => 'استدعاء المريض (عربي)',
            'en' => 'استدعاء المريض (إنجليزي)',
            'tr' => 'استدعاء المريض (تركي)',
        ], $this->titles($arabic));

        $turkish = Company::factory()->create(['language' => 'tr']);
        app(SystemMessageService::class)->seedForCompanySpecialty($turkish, $dental);
        $this->assertSame([
            'ar' => 'Hasta Çağrısı (Arapça)',
            'en' => 'Hasta Çağrısı (İngilizce)',
            'tr' => 'Hasta Çağrısı (Türkçe)',
        ], $this->titles($turkish));

        // The message bodies stay in their own language.
        $body = CustomMessage::query()->where('company_id', $turkish->id)->where('system_key', 'patient_recall')->where('language', 'ar')->value('body');
        $this->assertStringContainsString('مرحبًا', $body);
    }

    public function test_changing_the_company_language_retitles_untouched_system_messages_only(): void
    {
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $company = Company::factory()->create(['language' => 'tr']);
        app(SystemMessageService::class)->seedForCompanySpecialty($company, $dental);
        CustomMessage::query()->where('company_id', $company->id)->where('system_key', 'patient_recall')->where('language', 'tr')->update(['title' => 'Bizim özel başlık']);

        $this->actingAs($this->adminUser())->put(route('admin.companies.update', $company), [
            'name' => $company->name,
            'code' => $company->code,
            'status' => 'active',
            'currency' => 'TRY',
            'language' => 'en',
        ])->assertRedirect(route('admin.companies.index'));

        $this->assertSame([
            'ar' => 'Patient Recall (Arabic)',
            'en' => 'Patient Recall (English)',
            'tr' => 'Bizim özel başlık',
        ], $this->titles($company));
    }

    public function test_the_signed_in_user_payload_carries_the_company_language(): void
    {
        $company = Company::factory()->create(['language' => 'ar']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.company_language', 'ar');
    }
}
