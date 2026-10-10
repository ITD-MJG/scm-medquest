<?php

use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\Room;
use App\Services\BatchResolver;

// The brief hardcodes location_id = 1 on every resolve() call; batches.location_id is a
// non-null FK, so a location with that id must exist for those calls to be valid.
beforeEach(function () {
    Location::factory()->create(['id' => 1]);
});

it('normalises every none-like lot value to the empty string', function () {
    foreach ([null, '', '   ', 'N/A', 'n/a', '-'] as $input) {
        expect(BatchResolver::normalizeLotNo($input))->toBe('');
    }
});

it('keeps a real lot number', function () {
    expect(BatchResolver::normalizeLotNo(' 471000 '))->toBe('471000');
});

it('normalises every none-like expiry value to the sentinel', function () {
    foreach ([null, '', 'N/A', '-'] as $input) {
        expect(BatchResolver::normalizeExpiryDate($input))->toBe(Batch::EXPIRY_SENTINEL);
    }
});

it('creates a batch in quarantine when none matches', function () {
    $product = Product::factory()->create();

    $batch = app(BatchResolver::class)->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);

    expect($batch->exists)->toBeTrue()
        ->and($batch->status->value)->toBe('quarantine')
        ->and($batch->quantity)->toBe('5.00');
});

it('merges quantity into an existing batch', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $first = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $second = $resolver->resolve($product, '471000', '2025-12-31', 10.0, 'KIT', null, 1);

    expect($second->id)->toBe($first->id)
        ->and($second->fresh()->quantity)->toBe('15.00');
});

it('merges two N/A lot lines into one batch', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $first = $resolver->resolve($product, 'N/A', 'N/A', 200.0, 'PCS', null, 1);
    $second = $resolver->resolve($product, null, null, 50.0, 'PCS', null, 1);

    expect($second->id)->toBe($first->id)
        ->and(Batch::count())->toBe(1);
});

it('does not overwrite a room that is already set', function () {
    $product = Product::factory()->create();
    $original = Room::factory()->create();
    $replacement = Room::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $original, 1);
    $batch = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $replacement, 1);

    expect($batch->fresh()->room_id)->toBe($original->id);
});

it('sets the room when the batch has none', function () {
    $product = Product::factory()->create();
    $room = Room::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $batch = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $room, 1);

    expect($batch->fresh()->room_id)->toBe($room->id);
});

it('keeps separate batches for different lots of one product', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $resolver->resolve($product, '464000', '2025-12-31', 5.0, 'KIT', null, 1);

    expect(Batch::count())->toBe(2);
});
