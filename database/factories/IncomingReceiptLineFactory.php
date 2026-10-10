<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncomingReceiptLine>
 */
class IncomingReceiptLineFactory extends Factory
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
            'incoming_receipt_id' => IncomingReceipt::factory(),
            'batch_id' => Batch::factory(),
            'line_no' => 1,
            'internal_code' => strtoupper(fake()->bothify('??####')),
            'product_description' => fake()->sentence(),
            'lot_no' => strtoupper(Str::random(6)),
            'quantity' => 0,
            'uom' => 'PCS',
        ];
    }
}
