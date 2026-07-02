<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTreatmentProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_treatment_product_with_multilingual_payload(): void
    {
        [, $company] = $this->authenticatedUser();

        $this->postJson("/api/companies/{$company->id}/treatment-products", [
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'English',
            'name_tr' => 'Turkce',
            'color' => '#3b82f6',
            'price' => 100,
            'unit_price' => 100,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'tt')
            ->assertJsonPath('data.name_ar', 'ar')
            ->assertJsonPath('data.name_en', 'English')
            ->assertJsonPath('data.name_tr', 'Turkce')
            ->assertJsonPath('data.color', '#3b82f6')
            ->assertJsonPath('data.price', 100);

        $this->assertDatabaseHas('treatment_catalog', [
            'company_id' => $company->id,
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'English',
            'name_tr' => 'Turkce',
            'color' => '#3b82f6',
        ]);
    }

    public function test_it_updates_treatment_product_with_same_payload_shape(): void
    {
        [, $company] = $this->authenticatedUser();

        $product = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'code' => 'old',
            'name_ar' => 'old-ar',
            'name_en' => 'Old',
            'name_tr' => 'Eski',
            'color' => '#111111',
            'default_price' => 50,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->putJson("/api/companies/{$company->id}/treatment-products/{$product->id}", [
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'en',
            'name_tr' => 'tr',
            'color' => '#3b82f6',
            'price' => 100,
            'unit_price' => 100,
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.code', 'tt')
            ->assertJsonPath('data.name_ar', 'ar')
            ->assertJsonPath('data.name_en', 'en')
            ->assertJsonPath('data.name_tr', 'tr')
            ->assertJsonPath('data.color', '#3b82f6')
            ->assertJsonPath('data.price', 100);

        $this->assertDatabaseHas('treatment_catalog', [
            'id' => $product->id,
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'en',
            'name_tr' => 'tr',
            'color' => '#3b82f6',
            'default_price' => 100,
        ]);
    }

    protected function authenticatedUser(): array
    {
        $company = Company::factory()->create([
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_name' => 'Active Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 5,
            'active_users' => 1,
            'price' => 0,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        return [$user, $company];
    }
}
