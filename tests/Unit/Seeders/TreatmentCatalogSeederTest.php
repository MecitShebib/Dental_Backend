<?php

namespace Tests\Unit\Seeders;

use App\Models\Company;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Database\Seeders\TreatmentCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreatmentCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_company_and_odontogram_scoped_items_with_no_duplicate_codes(): void
    {
        $company = Company::factory()->create();

        (new TreatmentCatalogSeeder)->run();

        $items = TreatmentCatalog::query()->where('company_id', $company->id)->get();

        $this->assertGreaterThanOrEqual(50, $items->count());
        $this->assertSame($items->count(), $items->pluck('code')->unique()->count());

        $companyItems = $items->where('scope', TreatmentCatalog::SCOPE_COMPANY);
        $odontogramItems = $items->where('scope', TreatmentCatalog::SCOPE_ODONTOGRAM);

        $this->assertSame(6, $companyItems->count());
        $this->assertGreaterThanOrEqual(45, $odontogramItems->count());

        foreach ($items as $item) {
            $this->assertNotEmpty($item->name_en, "name_en missing for {$item->code}");
            $this->assertNotEmpty($item->name_ar, "name_ar missing for {$item->code}");
            $this->assertNotEmpty($item->name_tr, "name_tr missing for {$item->code}");
        }

        $this->assertTrue($odontogramItems->contains('code', 'fillingMaterial:composite'));
        $this->assertTrue($odontogramItems->contains('code', 'mods:caries'));
    }

    public function test_odontogram_prices_endpoint_only_returns_odontogram_scope(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        (new TreatmentCatalogSeeder)->run();

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/companies/{$company->id}/odontogram-treatment-prices")->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('fillingMaterial:composite', $data);
        $this->assertArrayNotHasKey('consultation', $data);
    }

    public function test_company_treatment_products_endpoint_returns_both_scopes_for_settings_pricing(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        (new TreatmentCatalogSeeder)->run();

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/companies/{$company->id}/treatment-products")->assertOk();

        $products = collect($response->json('data'));
        $this->assertTrue($products->pluck('code')->contains('consultation'));
        $this->assertTrue($products->pluck('code')->contains('fillingMaterial:composite'));
        $this->assertSame('company', $products->firstWhere('code', 'consultation')['scope']);
        $this->assertSame('odontogram', $products->firstWhere('code', 'fillingMaterial:composite')['scope']);
    }

    public function test_a_company_treatment_product_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        (new TreatmentCatalogSeeder)->run();

        $product = TreatmentCatalog::query()->where('company_id', $company->id)->where('code', 'consultation')->firstOrFail();

        Sanctum::actingAs($user);
        $this->deleteJson("/api/companies/{$company->id}/treatment-products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('treatment_catalog', ['id' => $product->id]);
    }

    public function test_a_user_cannot_delete_another_companys_treatment_product(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        (new TreatmentCatalogSeeder)->run();

        $product = TreatmentCatalog::query()->where('company_id', $otherCompany->id)->where('code', 'consultation')->firstOrFail();

        Sanctum::actingAs($user);
        $this->deleteJson("/api/companies/{$otherCompany->id}/treatment-products/{$product->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');

        $this->assertDatabaseHas('treatment_catalog', ['id' => $product->id]);
    }
}
