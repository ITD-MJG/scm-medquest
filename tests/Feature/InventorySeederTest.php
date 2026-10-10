<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\Room;
use Database\Seeders\InventoryMasterDataSeeder;

it('seeds the sample form products idempotently', function () {
    $this->seed(InventoryMasterDataSeeder::class);
    $this->seed(InventoryMasterDataSeeder::class);

    expect(Product::where('internal_code', 'DIS-T-00003')->count())->toBe(1)
        ->and(Product::where('internal_code', 'PAC-N-00008')->count())->toBe(1);
});

it('does not seed rooms that already exist', function () {
    $location = Location::factory()->create();
    Room::factory()->for($location)->create(['room_name' => 'Cold Storage 4']);
    $before = Room::count();

    $this->seed(InventoryMasterDataSeeder::class);

    expect(Room::where('room_name', 'Cold Storage 4')->count())->toBe(1)
        ->and(Room::count())->toBeGreaterThanOrEqual($before);
});
