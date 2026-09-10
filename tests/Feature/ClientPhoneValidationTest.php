<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_client_with_a_malformed_phone_number_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/clients', [
            'name' => 'Test Patient',
            'phone' => 'not-a-phone-number',
            'gender' => 'male',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phone');
    }

    public function test_creating_a_client_with_a_real_looking_phone_number_succeeds(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/clients', [
            'name' => 'Test Patient',
            'phone' => '+963 955 123 456',
            'gender' => 'male',
        ]);

        $response->assertCreated();
    }
}
