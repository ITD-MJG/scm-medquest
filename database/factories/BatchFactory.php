<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Eloquent does not hydrate database column defaults on create, so every
     * column the model reads directly is set here explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batchId' => Str::orderedUuid(),
            'product_id' => Product::factory(),
            'lot_no' => strtoupper(Str::random(6)),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'quantity' => 0,
            'uom' => 'PCS',
            'status' => BatchStatus::QUARANTINE->value,
            'location_id' => Location::factory(),
            'room_id' => null,
        ];
    }
}
