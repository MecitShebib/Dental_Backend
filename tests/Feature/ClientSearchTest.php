<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_search_by_name_returns_only_matching_clients(): void
    {
        Client::create(['client_code' => 'CL-1', 'name' => 'Ahmad Khatib', 'phone' => '0933111111', 'gender' => 'male', 'status' => 'new']);
        Client::create(['client_code' => 'CL-2', 'name' => 'Lina Abdo', 'phone' => '0944222222', 'gender' => 'female', 'status' => 'new']);

        $response = $this->getJson('/api/clients?name=Ahmad&per_page=20');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('Ahmad Khatib'));
        $this->assertFalse($names->contains('Lina Abdo'));
    }

    public function test_search_by_phone_returns_only_matching_clients(): void
    {
        Client::create(['client_code' => 'CL-1', 'name' => 'Ahmad Khatib', 'phone' => '0933111111', 'gender' => 'male', 'status' => 'new']);
        Client::create(['client_code' => 'CL-2', 'name' => 'Lina Abdo', 'phone' => '0944222222', 'gender' => 'female', 'status' => 'new']);

        $response = $this->getJson('/api/clients?phone=0933&per_page=20');

        $response->assertOk();
        $phones = collect($response->json('data.data'))->pluck('phone');
        $this->assertTrue($phones->contains('0933111111'));
        $this->assertFalse($phones->contains('0944222222'));
    }

    public function test_combined_name_and_phone_filters_narrow_results(): void
    {
        Client::create(['client_code' => 'CL-1', 'name' => 'Ahmad Khatib', 'phone' => '0933111111', 'gender' => 'male', 'status' => 'new']);
        Client::create(['client_code' => 'CL-2', 'name' => 'Ahmad Nassar', 'phone' => '0944222222', 'gender' => 'male', 'status' => 'new']);

        $response = $this->getJson('/api/clients?name=Ahmad&phone=0933&per_page=20');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertCount(1, $names);
        $this->assertTrue($names->contains('Ahmad Khatib'));
    }

    public function test_per_page_is_respected_and_returns_pagination_metadata(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Client::create(['client_code' => "CL-{$i}", 'name' => "Patient {$i}", 'phone' => "090000000{$i}", 'gender' => 'male', 'status' => 'new']);
        }

        $response = $this->getJson('/api/clients?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
        $this->assertSame(2, $response->json('data.meta.per_page'));
        $this->assertSame(3, $response->json('data.meta.total'));
        $this->assertNotNull($response->json('data.links.next'));
    }

    public function test_unfiltered_request_without_per_page_keeps_todays_flat_response_shape(): void
    {
        Client::create(['client_code' => 'CL-1', 'name' => 'Ahmad Khatib', 'phone' => '0933111111', 'gender' => 'male', 'status' => 'new']);

        $response = $this->getJson('/api/clients');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue(array_is_list($data), 'Expected a flat array of clients when per_page is omitted, matching pre-existing behavior.');
        $names = collect($data)->pluck('name');
        $this->assertTrue($names->contains('Ahmad Khatib'));
    }
}
