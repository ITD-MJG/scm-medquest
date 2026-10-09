<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'productId' => Str::orderedUuid(),
            'internal_code' => strtoupper(Str::random(3)).'-'.strtoupper(Str::random(1)).'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'principal_id' => null,
            'principal_code' => null,
            'description' => fake()->sentence(4),
            'part_no' => null,
            'uom' => 'PCS',
            'uom_source' => 'sap',
            'storage_requirement' => null,
            'source' => 'manual',
            'sap_code' => null,
            'is_active' => true,
        ];
    }
}
