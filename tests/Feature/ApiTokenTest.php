<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_list_api_tokens(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings/api-tokens', ['name' => 'X-Ray Machine'])
            ->assertCreated();

        $response->assertJsonPath('data.token.name', 'X-Ray Machine');
        $this->assertNotEmpty($response->json('data.plain_text_token'));

        $this->getJson('/api/settings/api-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'X-Ray Machine');
    }

    public function test_a_newly_created_api_token_can_authenticate_a_real_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $plainTextToken = $this->postJson('/api/settings/api-tokens', ['name' => 'Integration'])
            ->assertCreated()
            ->json('data.plain_text_token');

        // Simulate a genuinely separate, unauthenticated HTTP client (an
        // outside system) presenting only the bearer token -- not reusing
        // the Sanctum::actingAs() session from above.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid);
    }

    public function test_a_user_can_revoke_their_own_api_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/settings/api-tokens', ['name' => 'Temp'])
            ->assertCreated()->json('data.token.id');

        $this->deleteJson("/api/settings/api-tokens/{$id}")->assertOk();
        $this->getJson('/api/settings/api-tokens')->assertJsonCount(0, 'data');
    }

    public function test_a_user_cannot_revoke_another_users_api_token(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/settings/api-tokens', ['name' => 'Owned by first user'])
            ->assertCreated()->json('data.token.id');

        $otherUser = User::factory()->create(['company_id' => $owner->company_id]);
        Sanctum::actingAs($otherUser);

        $this->deleteJson("/api/settings/api-tokens/{$id}")->assertUnprocessable();
        Sanctum::actingAs($owner);
        $this->getJson('/api/settings/api-tokens')->assertJsonCount(1, 'data');
    }

    public function test_login_session_tokens_are_not_listed_as_api_tokens(): void
    {
        $user = User::factory()->create(['phone' => '905551234567']);
        // Mirrors AuthController's own token creation for a real login session.
        $user->createToken('api-token');

        Sanctum::actingAs($user);
        $this->getJson('/api/settings/api-tokens')->assertJsonCount(0, 'data');
    }

    public function test_api_token_name_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/settings/api-tokens', [])->assertUnprocessable();
    }
}
