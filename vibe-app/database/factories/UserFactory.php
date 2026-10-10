<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\SubMasterAccess;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** A Sub-master with the given access checklist (the default 13 areas when null). */
    public function subMaster(?array $access = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'sub_master',
            'status' => 'active',
            'organization_id' => null,
            'school_id' => null,
            'access' => $access ?? SubMasterAccess::defaults(),
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
