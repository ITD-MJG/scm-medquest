<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['location_code' => 'BP1', 'location_name' => 'Bizpark 1'],
            ['location_code' => 'BP2', 'location_name' => 'Bizpark 2'],
            ['location_code' => 'A35', 'location_name' => 'Bizpark A35'],
            ['location_code' => 'BDG', 'location_name' => 'Warehouse Bandung'],
            ['location_code' => 'SMG', 'location_name' => 'Warehouse Semarang'],
            ['location_code' => 'SBY', 'location_name' => 'Warehouse Surabaya'],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['location_code' => $location['location_code']],
                $location + ['locationId' => Str::orderedUuid()]
            );
        }
    }
}
