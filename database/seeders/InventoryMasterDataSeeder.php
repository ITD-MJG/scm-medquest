<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Room;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Location chosen consistently with RoomSeeder's storage rooms (Bizpark 1).
        $location = Location::where('location_name', 'Bizpark 1')->first()
            ?? Location::first();

        // Principals
        $diaspect = Principal::where('code', 'DIASPECT')->first()
            ?? Principal::create([
                'principalId' => Str::orderedUuid(),
                'code' => 'DIASPECT',
                'name' => 'DIASPECT',
                'is_active' => true,
            ]);

        // Products read off the sample forms, keyed by internal_code.
        // Descriptions not printed on the forms are left as the internal code
        // (placeholder) rather than invented product names.
        $products = [
            ['internal_code' => 'DIS-T-00003', 'description' => 'DIASPECT HEMOGLOBIN CUVETTES', 'part_no' => '9DC.0001', 'uom' => 'PCS', 'principal' => $diaspect],
            ['internal_code' => 'DIS-T-00007', 'description' => 'DIS-T-00007', 'part_no' => null, 'uom' => 'PCS', 'principal' => $diaspect],
            ['internal_code' => 'MDI-T-00001', 'description' => 'MDI-T-00001', 'part_no' => null, 'uom' => 'PCS', 'principal' => null],
            ['internal_code' => 'DIA-T-00076', 'description' => 'DIA-T-00076', 'part_no' => null, 'uom' => 'KIT', 'principal' => $diaspect],
            ['internal_code' => 'DIA-T-00077', 'description' => 'DIA-T-00077', 'part_no' => null, 'uom' => 'KIT', 'principal' => $diaspect],
            ['internal_code' => 'PAC-N-00008', 'description' => 'KARTON LEMBARAN DOUBLE K150/M X 4 UKURAN 48 X 31 CM', 'part_no' => null, 'uom' => 'PCS', 'principal' => null],
            ['internal_code' => 'GHL-T-00021', 'description' => 'GHL-T-00021', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00022', 'description' => 'GHL-T-00022', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00023', 'description' => 'GHL-T-00023', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00024', 'description' => 'GHL-T-00024', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00025', 'description' => 'GHL-T-00025', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00026', 'description' => 'GHL-T-00026', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
            ['internal_code' => 'GHL-T-00027', 'description' => 'GHL-T-00027', 'part_no' => null, 'uom' => 'KIT', 'principal' => null],
        ];

        foreach ($products as $item) {
            Product::updateOrCreate(
                ['internal_code' => $item['internal_code']],
                [
                    'productId' => Product::where('internal_code', $item['internal_code'])->value('productId') ?? Str::orderedUuid(),
                    'principal_id' => $item['principal']?->id,
                    'principal_code' => $item['principal']?->code,
                    'description' => $item['description'],
                    'part_no' => $item['part_no'],
                    'uom' => $item['uom'],
                    'uom_source' => 'manual',
                    'source' => 'manual',
                    'sap_code' => null,
                    'is_active' => true,
                ]
            );
        }

        // Rooms: only names the forms add beyond RoomSeeder.
        if ($location) {
            Room::firstOrCreate(
                ['location_id' => $location->id, 'room_name' => 'AREA PACKAGING'],
                ['roomId' => Str::orderedUuid()]
            );
        }

        // Carriers
        foreach (['PT. JAYA GUNA JATI', 'IOL LOGISTICS'] as $name) {
            Carrier::firstOrCreate(
                ['name' => $name],
                ['carrierId' => Str::orderedUuid(), 'is_active' => true]
            );
        }

        // Suppliers
        foreach (['PT. JAYA GUNA JATI', 'GRIFOLS - SINGAPORE', 'PT. MEDQUEST JAYA GLOBAL'] as $name) {
            Supplier::firstOrCreate(
                ['name' => $name],
                ['supplierId' => Str::orderedUuid(), 'is_active' => true]
            );
        }
    }
}
