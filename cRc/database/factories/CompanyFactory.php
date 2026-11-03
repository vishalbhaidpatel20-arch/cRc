<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'company_code' => fake()->unique()->bothify('CMP####'),
            'name' => fake()->unique()->company(),
            'industry' => fake()->word(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('079#######'),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
