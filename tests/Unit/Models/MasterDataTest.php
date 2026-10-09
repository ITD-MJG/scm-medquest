<?php

use App\Enums\ProductSource;
use App\Enums\StorageRequirement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts a product storage requirement to the enum', function () {
    $product = Product::factory()->create(['storage_requirement' => '2_8']);

    expect($product->fresh()->storage_requirement)->toBe(StorageRequirement::TWO_TO_EIGHT);
});

it('defaults a product source to manual', function () {
    expect(Product::factory()->create()->source)->toBe(ProductSource::MANUAL);
});

it('reuses a room as the storage area of a location', function () {
    $location = Location::factory()->create(['location_name' => 'Bizpark 1']);
    $room = Room::factory()->for($location)->create(['room_name' => 'Cold Storage 4']);

    expect($room->location->location_name)->toBe('Bizpark 1')
        ->and($room->room_name)->toBe('Cold Storage 4');
});

it('rejects a duplicate product internal code', function () {
    Product::factory()->create(['internal_code' => 'DIS-T-00003']);

    expect(fn () => Product::factory()->create(['internal_code' => 'DIS-T-00003']))
        ->toThrow(QueryException::class);
});
