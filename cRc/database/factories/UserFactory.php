<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** The current password being used by the factory. */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_AGENT,
            'all_companies' => true,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_AGENT]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Invited but has not set a password yet. */
    public function invited(): static
    {
        return $this->state(fn () => ['password_hash' => null]);
    }
}
