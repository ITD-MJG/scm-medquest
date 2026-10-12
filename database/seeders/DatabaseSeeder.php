<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            LocationSeeder::class,
            RoomSeeder::class,
            RoomTemperatureSeeder::class,
            DepartmentSeeder::class,
            SerialNumberSeeder::class,
            InventoryMasterDataSeeder::class,
        ]);
    }
}
