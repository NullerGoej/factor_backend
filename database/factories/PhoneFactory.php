<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class PhoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device' => $this->faker->word(),
            'ip_address' => $this->faker->ipv4(),
            'user_id' => $this->faker->unique()->numberBetween(1, 10),
            'two_factor_secret' => null,
            'two_factor_setup' => 0,
            'two_factor_6_digit' => null,
        ];
    }
}
