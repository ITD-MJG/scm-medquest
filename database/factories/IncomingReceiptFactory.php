<?php

namespace Database\Factories;

use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Models\IncomingReceipt;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncomingReceipt>
 */
class IncomingReceiptFactory extends Factory
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
            'receiptId' => Str::orderedUuid(),
            'receipt_no' => strtoupper(Str::random(12)),
            'receipt_date' => fake()->date(),
            'incoming_type' => IncomingType::NEW_INVENTORY_PO->value,
            'product_status' => ProductStatus::QUARANTINE->value,
            'location_id' => Location::factory(),
        ];
    }
}
