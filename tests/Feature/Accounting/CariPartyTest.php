<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CariPartyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(?Company $company = null): User
    {
        $company ??= Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $role = Role::query()->firstOrCreate(['slug' => 'system_manager'], ['name' => 'System Manager']);
        $manager->roles()->attach($role);

        return $manager;
    }

    public function test_a_manager_can_create_and_list_cari_parties(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Acme Dental Supplies',
            'phone' => '905551112233',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'supplier')
            // Regression: a freshly create()'d model doesn't pick up a
            // DB-level column default in memory, so this must come back
            // true from an explicit value, not the migration's default.
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/cari/parties', [
            'type' => 'health_agency',
            'name' => 'SGK',
        ])->assertCreated();

        $this->getJson('/api/cari/parties')->assertJsonCount(2, 'data');
        $this->getJson('/api/cari/parties?type=supplier')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Dental Supplies');
    }

    public function test_a_cari_party_can_be_updated_and_deleted(): void
    {
        $manager = $this->makeManager();
        Sanctum::actingAs($manager);

        $partyId = $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Old Name',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/cari/parties/{$partyId}", [
            'type' => 'supplier',
            'name' => 'New Name',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        // Inactive parties are hidden from the default listing.
        $this->getJson('/api/cari/parties')->assertJsonCount(0, 'data');
        $this->getJson('/api/cari/parties?include_inactive=1')->assertJsonCount(1, 'data');

        $this->deleteJson("/api/cari/parties/{$partyId}")->assertOk();
        $this->getJson('/api/cari/parties?include_inactive=1')->assertJsonCount(0, 'data');
    }

    public function test_cari_parties_are_scoped_to_the_companys_own_data(): void
    {
        $otherManager = $this->makeManager();
        Sanctum::actingAs($otherManager);
        $otherPartyId = $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Other Co Supplier',
        ])->assertCreated()->json('data.id');

        $ownManager = $this->makeManager();
        Sanctum::actingAs($ownManager);

        $this->getJson('/api/cari/parties')->assertJsonCount(0, 'data');
        $this->putJson("/api/cari/parties/{$otherPartyId}", [
            'type' => 'supplier',
            'name' => 'Hijacked',
        ])->assertNotFound();
    }

    public function test_a_regular_user_cannot_manage_cari_parties(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/cari/parties', [
            'type' => 'supplier',
            'name' => 'Nope',
        ])->assertStatus(422)->assertJsonValidationErrors('user');
    }
}
