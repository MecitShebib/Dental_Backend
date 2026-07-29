<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_view_their_own_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$company->id}")->assertOk();
    }

    public function test_a_user_cannot_view_another_companys_details(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$otherCompany->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');
    }

    public function test_a_user_can_view_their_own_companys_subscriptions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();
    }

    public function test_a_user_cannot_view_another_companys_subscriptions(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Subscription::create([
            'company_id' => $otherCompany->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$otherCompany->id}/subscriptions")
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');
    }

    public function test_a_project_admin_can_view_any_companys_details(): void
    {
        $company = Company::factory()->create();
        $projectAdmin = User::factory()->create(['company_id' => null, 'is_project_admin' => true]);
        Sanctum::actingAs($projectAdmin);

        $this->getJson("/api/companies/{$company->id}")->assertOk();
    }

    public function test_a_project_admin_can_view_any_companys_subscriptions(): void
    {
        $company = Company::factory()->create();
        $projectAdmin = User::factory()->create(['company_id' => null, 'is_project_admin' => true]);
        Subscription::create([
            'company_id' => $company->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);
        Sanctum::actingAs($projectAdmin);

        $this->getJson("/api/companies/{$company->id}/subscriptions")->assertOk();
    }

    public function test_a_user_cannot_view_another_companys_treatment_products(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$otherCompany->id}/treatment-products")
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');
    }

    public function test_a_user_cannot_view_another_companys_odontogram_prices(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/companies/{$otherCompany->id}/odontogram-treatment-prices")
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');
    }

    public function test_a_user_cannot_create_a_treatment_product_for_another_company(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/companies/{$otherCompany->id}/treatment-products", [
            'code' => 'sneaky',
            'name_en' => 'Sneaky',
            'name_ar' => 'خفي',
            'price' => 100,
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company');
    }
}
