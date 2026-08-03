<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabPartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_list_lab_partners(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/lab-partners', [
            'name' => 'Bright Smile Dental Lab',
            'phone' => '+90555000000',
            'email' => 'lab@example.com',
        ])->assertCreated()->assertJsonPath('data.name', 'Bright Smile Dental Lab');

        $this->getJson('/api/lab-partners')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_lab_partner_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/lab-partners', ['name' => 'Original Lab'])
            ->assertCreated()->json('data.id');

        $this->putJson("/api/lab-partners/{$id}", ['name' => 'Renamed Lab', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Lab')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/lab-partners/{$id}")->assertOk();
        $this->getJson('/api/lab-partners')->assertJsonCount(0, 'data');
    }

    public function test_lab_partners_are_scoped_to_the_companys_own_data(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherUser);
        $otherLabId = $this->postJson('/api/lab-partners', ['name' => 'Other Co Lab'])
            ->assertCreated()->json('data.id');

        $ownUser = User::factory()->create(['company_id' => $ownCompany->id]);
        Sanctum::actingAs($ownUser);

        $this->getJson('/api/lab-partners')->assertJsonCount(0, 'data');
        $this->putJson("/api/lab-partners/{$otherLabId}", ['name' => 'Hijacked'])->assertNotFound();
    }
}
