<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\LegalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_who_has_not_accepted_the_terms_is_blocked_from_the_clinic_api(): void
    {
        Sanctum::actingAs(User::factory()->termsNotAccepted()->create());

        $this->getJson('/api/doctors')
            ->assertStatus(403)
            ->assertJsonPath('code', 'terms_not_accepted')
            ->assertJsonPath('terms_version', LegalContent::TERMS_VERSION);

        // Specialty route files are gated too, not just routes/api.php.
        $this->getJson('/api/gynecology/dashboard/stats')
            ->assertStatus(403)
            ->assertJsonPath('code', 'terms_not_accepted');
    }

    public function test_auth_me_stays_reachable_and_reports_that_acceptance_is_required(): void
    {
        Sanctum::actingAs(User::factory()->termsNotAccepted()->create());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.requires_terms_acceptance', true)
            ->assertJsonPath('data.terms_version', LegalContent::TERMS_VERSION);
    }

    public function test_accepting_the_current_terms_unlocks_the_api_and_is_audit_logged(): void
    {
        $user = User::factory()->termsNotAccepted()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/accept-terms', [
            'version' => LegalContent::TERMS_VERSION,
            'personal_account_confirmed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.requires_terms_acceptance', false);

        $user->refresh();
        $this->assertSame(LegalContent::TERMS_VERSION, $user->terms_accepted_version);
        $this->assertNotNull($user->terms_accepted_at);

        $log = AuditLog::query()->where('action', 'terms_accepted')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['version' => LegalContent::TERMS_VERSION], $log->meta);

        $this->getJson('/api/doctors')->assertOk();
    }

    public function test_acceptance_requires_the_current_version_and_the_personal_account_confirmation(): void
    {
        Sanctum::actingAs(User::factory()->termsNotAccepted()->create());

        $this->postJson('/api/auth/accept-terms', ['version' => '1999-01-01', 'personal_account_confirmed' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');

        $this->postJson('/api/auth/accept-terms', ['version' => LegalContent::TERMS_VERSION, 'personal_account_confirmed' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('personal_account_confirmed');
    }

    public function test_an_older_accepted_version_must_be_accepted_again(): void
    {
        Sanctum::actingAs(User::factory()->create(['terms_accepted_version' => '2000-01-01']));

        $this->getJson('/api/doctors')->assertStatus(403)->assertJsonPath('code', 'terms_not_accepted');
    }

    public function test_integration_tokens_are_not_blocked_by_the_terms_gate(): void
    {
        $user = User::factory()->termsNotAccepted()->create();
        $token = $user->createToken('integration:PACS')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/doctors');

        $this->assertNotSame('terms_not_accepted', $response->json('code'));
    }
}
