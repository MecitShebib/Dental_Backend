<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'is_project_admin' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Shape for POSTing to admin.subscriptions.store (specialty_ids, plural
     * -- the form now supports selecting several specialties at once).
     */
    private function baseSubscriptionPayload(Company $company, Specialty $specialty, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'specialty_ids' => [$specialty->id],
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_branches' => 1,
        ], $overrides);
    }

    /**
     * Shape for creating a pre-existing row directly via Eloquent (the
     * subscriptions table itself still has one specialty_id, singular, per
     * row -- only the admin *form* creates several rows at once now).
     */
    private function createExistingSubscription(Company $company, Specialty $specialty, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_branches' => 1,
        ], $overrides));
    }

    public function test_creating_a_subscription_persists_the_chosen_specialty(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        $response = $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $gynecology),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'specialty_id' => $gynecology->id,
            'plan_name' => 'Test Plan',
        ]);
    }

    public function test_creating_a_subscription_seeds_that_specialtys_treatment_catalog(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();

        $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $gynecology),
        );

        $this->assertDatabaseHas('treatment_catalog', [
            'company_id' => $company->id,
            'specialty_id' => $gynecology->id,
            'code' => 'prenatal_checkup',
        ]);
    }

    public function test_a_company_cannot_have_two_active_subscriptions_for_the_same_specialty(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $this->createExistingSubscription($company, $dental, ['plan_name' => 'First']);

        // admin.subscriptions.index has no registered GET route (pre-existing
        // gap, unrelated to this change) -- the real, reachable flow is the
        // "Create Subscription" modal on the company's own admin page.
        $response = $this->actingAs($this->adminUser())->from(route('admin.companies.show', $company))->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $dental, ['plan_name' => 'Second']),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $response->assertSessionHasErrors('specialty_ids');
        $this->assertSame(1, Subscription::query()->where('company_id', $company->id)->where('specialty_id', $dental->id)->count());
    }

    public function test_two_different_specialties_can_both_be_active_for_the_same_company(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $this->createExistingSubscription($company, $dental);

        $response = $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $gynecology),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(2, $company->activeSpecialties()->count());
    }

    public function test_an_inactive_duplicate_specialty_subscription_does_not_trigger_the_guard(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $this->createExistingSubscription($company, $dental, ['status' => 'inactive']);

        $response = $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $dental),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(2, Subscription::query()->where('company_id', $company->id)->count());
    }

    public function test_selecting_multiple_specialties_creates_one_subscription_per_specialty(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $orthopedics = Specialty::query()->where('key', Specialty::ORTHOPEDICS)->firstOrFail();

        $response = $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $dental, [
                'specialty_ids' => [$dental->id, $gynecology->id, $orthopedics->id],
            ]),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(3, Subscription::query()->where('company_id', $company->id)->count());
        foreach ([$dental->id, $gynecology->id, $orthopedics->id] as $specialtyId) {
            $this->assertDatabaseHas('subscriptions', [
                'company_id' => $company->id,
                'specialty_id' => $specialtyId,
                'plan_name' => 'Test Plan',
            ]);
        }
    }

    public function test_a_multi_specialty_submission_is_rejected_entirely_if_any_one_specialty_already_has_an_active_subscription(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $this->createExistingSubscription($company, $dental, ['plan_name' => 'Existing Dental Plan']);

        $response = $this->actingAs($this->adminUser())->post(
            route('admin.subscriptions.store'),
            $this->baseSubscriptionPayload($company, $dental, [
                'specialty_ids' => [$gynecology->id, $dental->id],
            ]),
        );

        $response->assertSessionHasErrors('specialty_ids');
        // Neither the new gynecology row nor a duplicate dental row was created --
        // the whole submission is all-or-nothing.
        $this->assertSame(1, Subscription::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseMissing('subscriptions', ['company_id' => $company->id, 'specialty_id' => $gynecology->id]);
    }

    /**
     * Shape for PUTing to admin.subscriptions.update.
     */
    private function updateSubscriptionPayload(Subscription $subscription, array $specialtyIds, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $subscription->company_id,
            'specialty_ids' => $specialtyIds,
            'plan_name' => $subscription->plan_name,
            'status' => 'active',
            'starts_at' => $subscription->starts_at->toDateString(),
            'max_users' => $subscription->max_users,
            'max_branches' => $subscription->max_branches,
        ], $overrides);
    }

    public function test_updating_a_subscription_with_an_extra_checked_specialty_creates_a_sibling_row(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $subscription = $this->createExistingSubscription($company, $dental);

        $response = $this->actingAs($this->adminUser())->put(
            route('admin.subscriptions.update', $subscription),
            $this->updateSubscriptionPayload($subscription, [$dental->id, $gynecology->id], ['plan_name' => 'Renamed Plan']),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame(2, Subscription::query()->where('company_id', $company->id)->count());
        // The original row was updated in place, still dental.
        $this->assertSame('Renamed Plan', $subscription->fresh()->plan_name);
        $this->assertSame($dental->id, $subscription->fresh()->specialty_id);
        // A new sibling row was created for the newly checked specialty.
        $this->assertDatabaseHas('subscriptions', ['company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Renamed Plan']);
    }

    public function test_the_edited_rows_own_specialty_never_changes_regardless_of_checkbox_submission_order(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        // Editing a GYNECOLOGY row -- dental (which sorts earlier and would
        // be a checkbox appearing first in the form) is the *new* extra one.
        $subscription = $this->createExistingSubscription($company, $gynecology);

        $response = $this->actingAs($this->adminUser())->put(
            route('admin.subscriptions.update', $subscription),
            // Submitted with dental's id BEFORE gynecology's, matching real
            // checkbox DOM order (sort_order) rather than "which one is the
            // row being edited" -- this must not reassign the existing row.
            $this->updateSubscriptionPayload($subscription, [$dental->id, $gynecology->id]),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame($gynecology->id, $subscription->fresh()->specialty_id);
        $this->assertDatabaseHas('subscriptions', ['company_id' => $company->id, 'specialty_id' => $dental->id]);
    }

    public function test_updating_with_an_extra_specialty_that_already_has_an_active_subscription_is_rejected_entirely(): void
    {
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $gynecology = Specialty::query()->where('key', Specialty::GYNECOLOGY)->firstOrFail();
        $subscription = $this->createExistingSubscription($company, $dental);
        $this->createExistingSubscription($company, $gynecology, ['plan_name' => 'Existing Gyne Plan']);

        $response = $this->actingAs($this->adminUser())->put(
            route('admin.subscriptions.update', $subscription),
            $this->updateSubscriptionPayload($subscription, [$dental->id, $gynecology->id], ['plan_name' => 'Should Not Apply']),
        );

        $response->assertSessionHasErrors('specialty_ids');
        $this->assertSame(2, Subscription::query()->where('company_id', $company->id)->count());
        // Neither existing row was touched.
        $this->assertNotSame('Should Not Apply', $subscription->fresh()->plan_name);
        $this->assertDatabaseHas('subscriptions', ['company_id' => $company->id, 'specialty_id' => $gynecology->id, 'plan_name' => 'Existing Gyne Plan']);
    }
}
