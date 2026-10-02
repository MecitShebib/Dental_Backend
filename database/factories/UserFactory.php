<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Support\LegalContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'branch_name' => fake()->city(),
            'status' => 'active',
            'is_project_admin' => false,
            'is_doctor' => false,
            // Explicit rather than relying on the column's DB-level default
            // (true) -- Eloquent's create() doesn't reload DB-computed
            // defaults into the returned in-memory instance, and tests
            // widely reuse that exact instance via Sanctum::actingAs()
            // without a fresh DB round-trip, so an implicit default would
            // read back as null (falsy) here even though the real row is 1.
            'ai_enabled' => true,
            'notes' => null,
            // Accepted by default so the API isn't blocked by
            // EnsureTermsAccepted in every test; use termsNotAccepted() to
            // exercise the acceptance gate itself.
            'terms_accepted_version' => LegalContent::TERMS_VERSION,
            'terms_accepted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function termsNotAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_accepted_version' => null,
            'terms_accepted_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
