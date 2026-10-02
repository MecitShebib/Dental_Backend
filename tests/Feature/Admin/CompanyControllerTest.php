<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConsentTemplate;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
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

    public function test_creating_a_company_automatically_seeds_its_treatment_catalog(): void
    {
        $response = $this->actingAs($this->adminUser())->post('/admin/companies', [
            'name' => 'New Clinic',
            'code' => 'NEW-01',
            'status' => 'active',
            'currency' => 'TRY',
            'language' => 'tr',
        ]);

        $response->assertRedirect(route('admin.companies.index'));

        $company = Company::query()->where('code', 'NEW-01')->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->get();

        $this->assertGreaterThanOrEqual(100, $items->count());
        $this->assertTrue($items->where('scope', TreatmentCatalog::SCOPE_COMPANY)->contains('code', 'consultation'));
        $this->assertTrue($items->where('scope', TreatmentCatalog::SCOPE_ODONTOGRAM)->contains('code', 'fillingMaterial:composite'));
    }

    public function test_creating_a_company_automatically_seeds_its_kvkk_consent_templates(): void
    {
        $response = $this->actingAs($this->adminUser())->post('/admin/companies', [
            'name' => 'KVKK Clinic',
            'code' => 'NEW-KVKK',
            'status' => 'active',
            'currency' => 'TRY',
            'language' => 'tr',
        ]);

        $response->assertRedirect(route('admin.companies.index'));

        $company = Company::query()->where('code', 'NEW-KVKK')->firstOrFail();
        $templates = ConsentTemplate::query()->where('company_id', $company->id)->get();

        $this->assertTrue($templates->contains('kind', ConsentTemplate::KIND_KVKK_DISCLOSURE));
        $this->assertTrue($templates->contains('kind', ConsentTemplate::KIND_KVKK_EXPLICIT_CONSENT));
    }

    public function test_creating_a_company_automatically_seeds_a_default_branch(): void
    {
        $response = $this->actingAs($this->adminUser())->post('/admin/companies', [
            'name' => 'Another Clinic',
            'code' => 'NEW-02',
            'status' => 'active',
            'currency' => 'TRY',
            'language' => 'tr',
        ]);

        $response->assertRedirect(route('admin.companies.index'));

        $company = Company::query()->where('code', 'NEW-02')->firstOrFail();
        $branches = Branch::query()->where('company_id', $company->id)->get();

        $this->assertCount(1, $branches);
        $this->assertSame('Main Branch', $branches->first()->name);
        $this->assertSame('active', $branches->first()->status);
    }

    public function test_deleting_a_company_soft_deletes_it_and_cascades_to_its_users_and_subscriptions(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($this->adminUser())->delete(route('admin.companies.destroy', $company));

        $response->assertRedirect(route('admin.companies.index'));

        // Nothing is a real SQL DELETE -- every row must still be findable
        // via withTrashed(), just excluded from normal (non-trashed) queries.
        $this->assertSoftDeleted($company);
        $this->assertSoftDeleted($staff);
        $this->assertSoftDeleted($subscription);
        $this->assertNull(Company::find($company->id));
        $this->assertNull(User::find($staff->id));
        $this->assertNull(Subscription::find($subscription->id));
    }

    public function test_a_deleted_company_still_appears_in_the_index_listing_with_its_users_intact(): void
    {
        $company = Company::factory()->create(['name' => 'Soon Deleted Clinic']);
        User::factory()->create(['company_id' => $company->id]);
        $company->delete();

        $response = $this->actingAs($this->adminUser())->get(route('admin.companies.index'));

        $response->assertOk();
        $response->assertViewHas('companies', function ($companies) use ($company) {
            $found = $companies->firstWhere('id', $company->id);

            // Still 1, not 0 -- the cascade only soft-deletes the user, and
            // the relation used for this count must still resolve it (as
            // trashed) rather than silently dropping it from the collection.
            return $found && $found->trashed() && $found->users->count() === 1;
        });
    }

    public function test_a_deleted_companys_show_page_is_still_reachable_and_lists_its_trashed_users(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Trashed Staffer']);
        $company->delete();
        $staff->delete();

        $response = $this->actingAs($this->adminUser())->get(route('admin.companies.show', $company));

        $response->assertOk();
        $response->assertViewHas('company', fn ($viewCompany) => $viewCompany->trashed());
        $response->assertViewHas('users', fn ($users) => $users->contains('id', $staff->id));
    }

    public function test_restoring_a_company_cascades_to_the_users_and_subscriptions_it_took_down(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);
        $this->actingAs($this->adminUser())->delete(route('admin.companies.destroy', $company));

        $response = $this->actingAs($this->adminUser())->patch(route('admin.companies.restore', $company));

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertNotNull(Company::find($company->id));
        $this->assertNotNull(User::find($staff->id));
        $this->assertNotNull(Subscription::find($subscription->id));
    }

    public function test_a_user_can_be_restored_independently_of_its_company(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->delete();

        $response = $this->actingAs($this->adminUser())->patch(route('admin.users.restore', $staff));

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertNotNull(User::find($staff->id));
        $this->assertNotNull(Company::find($company->id), 'company was never deleted in this scenario');
    }

    public function test_force_deleting_a_company_permanently_erases_it_with_its_users_and_subscriptions(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);
        $this->actingAs($this->adminUser())->delete(route('admin.companies.destroy', $company));

        $response = $this->actingAs($this->adminUser())->delete(route('admin.companies.force-delete', $company));

        $response->assertRedirect(route('admin.companies.index'));
        $this->assertNull(Company::withTrashed()->find($company->id));
        $this->assertNull(User::withTrashed()->find($staff->id));
        $this->assertNull(Subscription::withTrashed()->find($subscription->id));
    }

    public function test_force_deleting_a_company_also_erases_its_clients_and_appointments(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-FORCE-DEL',
            'name' => 'Soon Erased Patient',
            'phone' => '+905551234567',
            'gender' => 'male',
            'status' => 'new',
        ]);
        $appointment = Appointment::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => now()->toDateString(),
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        $this->actingAs($this->adminUser())->delete(route('admin.companies.destroy', $company));

        $this->actingAs($this->adminUser())->delete(route('admin.companies.force-delete', $company));

        $this->assertNull(Client::withTrashed()->find($client->id));
        $this->assertNull(Appointment::withTrashed()->find($appointment->id));
    }

    public function test_a_company_must_already_be_soft_deleted_before_it_can_be_force_deleted(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->adminUser())->delete(route('admin.companies.force-delete', $company));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNotNull(Company::find($company->id));
    }

    public function test_force_deleting_a_user_independently_of_its_company(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->delete();

        $response = $this->actingAs($this->adminUser())->delete(route('admin.users.force-delete', $staff));

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertNull(User::withTrashed()->find($staff->id));
        $this->assertNotNull(Company::find($company->id));
    }

    public function test_a_user_must_already_be_soft_deleted_before_it_can_be_force_deleted(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($this->adminUser())->delete(route('admin.users.force-delete', $staff));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNotNull(User::find($staff->id));
    }

    public function test_force_deleting_a_doctor_with_appointments_on_file_fails_gracefully(): void
    {
        $company = Company::factory()->create();
        $doctor = User::factory()->create(['company_id' => $company->id, 'is_doctor' => true]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-RESTRICT',
            'name' => 'Still Has A Doctor',
            'phone' => '+905557654321',
            'gender' => 'male',
            'status' => 'new',
        ]);
        Appointment::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'doctor_id' => $doctor->id,
            'type' => 'booked', 'status' => 'scheduled', 'date' => now()->toDateString(),
            'start_time' => '10:00:00', 'duration_minutes' => 30,
        ]);
        $doctor->delete();

        $response = $this->actingAs($this->adminUser())->delete(route('admin.users.force-delete', $doctor));

        $response->assertRedirect(route('admin.companies.show', $company));
        $response->assertSessionHas('error');
        $this->assertNotNull(User::withTrashed()->find($doctor->id));
    }

    public function test_force_deleting_a_subscription_independently_of_its_company(): void
    {
        $company = Company::factory()->create();
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);
        $subscription->delete();

        $response = $this->actingAs($this->adminUser())->delete(route('admin.subscriptions.force-delete', $subscription));

        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertNull(Subscription::withTrashed()->find($subscription->id));
    }

    public function test_a_subscription_must_already_be_soft_deleted_before_it_can_be_force_deleted(): void
    {
        $company = Company::factory()->create();
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($this->adminUser())->delete(route('admin.subscriptions.force-delete', $subscription));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNotNull(Subscription::find($subscription->id));
    }

    public function test_a_failed_create_redirects_back_with_errors_scoped_to_the_create_modal_and_a_reopen_hint(): void
    {
        $response = $this->actingAs($this->adminUser())->from(route('admin.companies.index'))->post('/admin/companies', [
            'name' => '',
            'code' => '',
            'status' => 'active',
            '_modal_id' => 'create-company-modal',
        ]);

        $response->assertRedirect(route('admin.companies.index'));
        $response->assertSessionHasErrors(['name', 'code'], null, 'create-company-modal');
        $response->assertSessionHasInput('_modal_id', 'create-company-modal');
    }

    public function test_a_failed_update_scopes_its_errors_to_that_companys_own_modal_id_not_a_shared_bag(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->adminUser())->from(route('admin.companies.index'))->put(
            route('admin.companies.update', $company),
            ['name' => '', 'code' => '', 'status' => 'active', '_modal_id' => 'update-company-'.$company->id],
        );

        $response->assertRedirect(route('admin.companies.index'));
        $response->assertSessionHasErrors(['name', 'code'], null, 'update-company-'.$company->id);
        $response->assertSessionHasInput('_modal_id', 'update-company-'.$company->id);

        // Nothing lands in the unnamed 'default' bag -- a *different*
        // company's identically-shaped modal on the same page reads
        // @error('name') against its own "update-company-{other id}" bag
        // (or nothing at all), never this one.
        $errorBags = session('errors');
        $this->assertTrue($errorBags->getBag('default')->isEmpty());
    }

    public function test_a_malformed_phone_number_is_rejected_on_create(): void
    {
        $response = $this->actingAs($this->adminUser())->post('/admin/companies', [
            'name' => 'Bad Phone Clinic',
            'code' => 'BAD-PHONE',
            'phone' => 'call-me-maybe',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('companies', ['code' => 'BAD-PHONE']);
    }

    public function test_the_reopen_modal_script_renders_after_the_dialogs_it_looks_up_by_id(): void
    {
        // Regression test for a real bug: the reopen-on-error script used to
        // sit in the *first* <script> block, above @stack('modals') in
        // source order. getElementById() for a dialog that hadn't been
        // parsed into the DOM yet always returned null, so the script ran,
        // found nothing, and silently did nothing -- from the user's side
        // this looked exactly like "the popup just closes on every save,
        // error or not", since a failed submit never visibly reopened it.
        $response = $this->actingAs($this->adminUser())->get(route('admin.companies.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['id="create-company-modal"', 'reopenModalId'], false);

        // Regression test for a second, related bug: writing the literal
        // text "@stack('modals')" inside a plain // comment (to explain why
        // the reopen script has to come after it) made Blade's compiler
        // match it as a second real @stack('modals') call right there in
        // the comment -- silently duplicating every dialog on the page.
        // getElementById() only ever finds the first of two same-id
        // elements, so this didn't break the reopen itself, but every
        // dialog's own content (including the inline @error messages)
        // rendered twice.
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="create-company-modal"'));
    }

    public function test_show_page_lists_the_companys_users_for_a_project_admin_with_no_company(): void
    {
        // Regression test: Sanctum's package default (config/sanctum.php
        // 'guard' => ['web']) makes auth('sanctum') check the session-based
        // 'web' guard before the bearer token. Since the admin panel IS a
        // 'web'-guard session, that made BelongsToCompany's global scope
        // wrongly activate on admin-panel requests, scoping every query to
        // the logged-in admin's own company_id -- null for a project admin,
        // so the "Company Users" table came up empty for every company.
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id, 'name' => 'Regression Staff']);

        $response = $this->actingAs($this->adminUser())->get(route('admin.companies.show', $company));

        $response->assertOk();
        $response->assertViewHas('users', function ($users) use ($staff) {
            return $users->contains('id', $staff->id);
        });
    }
}
