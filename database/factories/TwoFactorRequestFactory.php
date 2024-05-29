<?php

namespace Database\Factories;

use App\Models\TwoFactorRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TwoFactorRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TwoFactorRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'unique_id' => Str::random(10),
            'accepted' => false,
            'ip_address' => $this->faker->ipv4,
            'action' => $this->faker->word,
            'device_id' => $this->faker->unique()->numberBetween(1, 10),
        ];
    }
}