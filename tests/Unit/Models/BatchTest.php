<?php

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\QueryException;

it('defaults a new batch to quarantine', function () {
    expect(Batch::factory()->create()->status)->toBe(BatchStatus::QUARANTINE);
});

it('rejects two batches with the same product, lot and expiry', function () {
    $product = Product::factory()->create();

    Batch::factory()->for($product)->create(['lot_no' => '471000', 'expiry_date' => '2025-12-31']);

    expect(fn () => Batch::factory()->for($product)->create(['lot_no' => '471000', 'expiry_date' => '2025-12-31']))
        ->toThrow(QueryException::class);
});

it('allows repeated batches with an empty lot and sentinel expiry', function () {
    $product = Product::factory()->create();

    Batch::factory()->for($product)->create(['lot_no' => '', 'expiry_date' => Batch::EXPIRY_SENTINEL]);

    expect(fn () => Batch::factory()->for($product)->create(['lot_no' => '', 'expiry_date' => Batch::EXPIRY_SENTINEL]))
        ->toThrow(QueryException::class);
});

it('prints the sentinel expiry as N/A', function () {
    $batch = Batch::factory()->create(['expiry_date' => Batch::EXPIRY_SENTINEL]);

    expect($batch->expiryDateLabel())->toBe('N/A');
});

it('prints an empty lot as N/A', function () {
    $batch = Batch::factory()->create(['lot_no' => '']);

    expect($batch->lotNoLabel())->toBe('N/A');
});

it('prints a real expiry in d M Y', function () {
    $batch = Batch::factory()->create(['expiry_date' => '2027-08-15']);

    expect($batch->expiryDateLabel())->toBe('15 Aug 2027');
});
