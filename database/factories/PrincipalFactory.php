<?php

namespace Database\Factories;

use App\Models\Principal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Principal>
 */
class PrincipalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'principalId' => Str::orderedUuid(),
            'code' => strtoupper(Str::random(8)),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
