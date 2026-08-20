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
            'price' => 100,
            'unit_price' => 100,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'tt')
            ->assertJsonPath('data.name_ar', 'ar')
            ->assertJsonPath('data.name_en', 'English')
            ->assertJsonPath('data.name_tr', 'Turkce')
            ->assertJsonPath('data.price', 100);

        $this->assertDatabaseHas('treatment_catalog', [
            'company_id' => $company->id,
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'English',
            'name_tr' => 'Turkce',
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
            'default_price' => 50,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->putJson("/api/companies/{$company->id}/treatment-products/{$product->id}", [
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'en',
            'name_tr' => 'tr',
            'price' => 100,
            'unit_price' => 100,
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.code', 'tt')
            ->assertJsonPath('data.name_ar', 'ar')
            ->assertJsonPath('data.name_en', 'en')
            ->assertJsonPath('data.name_tr', 'tr')
            ->assertJsonPath('data.price', 100);

        $this->assertDatabaseHas('treatment_catalog', [
            'id' => $product->id,
            'code' => 'tt',
            'name_ar' => 'ar',
            'name_en' => 'en',
            'name_tr' => 'tr',
            'default_price' => 100,
        ]);
    }

    public function test_it_applies_a_percentage_increase_to_every_catalog_item(): void
    {
        [, $company] = $this->authenticatedUser();

        $companyScoped = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'scope' => TreatmentCatalog::SCOPE_COMPANY,
            'code' => 'consultation',
            'name_ar' => 'ar', 'name_en' => 'Consultation', 'name_tr' => 'tr',
            'default_price' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $odontogramScoped = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'scope' => TreatmentCatalog::SCOPE_ODONTOGRAM,
            'code' => 'fillingMaterial:composite',
            'name_ar' => 'ar', 'name_en' => 'Composite filling', 'name_tr' => 'tr',
            'default_price' => 50,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->postJson("/api/companies/{$company->id}/treatment-products/bulk-price-adjustment", [
            'type' => 'percentage',
            'value' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('treatment_catalog', ['id' => $companyScoped->id, 'default_price' => 110]);
        $this->assertDatabaseHas('treatment_catalog', ['id' => $odontogramScoped->id, 'default_price' => 55]);
    }

    public function test_it_applies_a_fixed_amount_decrease_and_clamps_at_zero(): void
    {
        [, $company] = $this->authenticatedUser();

        $product = TreatmentCatalog::query()->create([
            'company_id' => $company->id,
            'code' => 'cheap-item',
            'name_ar' => 'ar', 'name_en' => 'Cheap item', 'name_tr' => 'tr',
            'default_price' => 20,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->postJson("/api/companies/{$company->id}/treatment-products/bulk-price-adjustment", [
            'type' => 'fixed',
            'value' => -50,
        ])->assertOk();

        $this->assertDatabaseHas('treatment_catalog', ['id' => $product->id, 'default_price' => 0]);
    }

    public function test_bulk_price_adjustment_rejects_a_percentage_that_would_zero_out_or_invert_prices(): void
    {
        [, $company] = $this->authenticatedUser();

        $this->postJson("/api/companies/{$company->id}/treatment-products/bulk-price-adjustment", [
            'type' => 'percentage',
            'value' => -100,
        ])->assertJsonValidationErrors('value');
    }

    public function test_bulk_price_adjustment_is_scoped_to_the_requesters_company(): void
    {
        [, $company] = $this->authenticatedUser();
        $otherCompany = Company::factory()->create(['status' => 'active']);

        $this->postJson("/api/companies/{$otherCompany->id}/treatment-products/bulk-price-adjustment", [
            'type' => 'percentage',
            'value' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('company');
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
