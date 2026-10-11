<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Supply Chain Manager']);
        Role::create(['name' => 'Supply Chain Officer']);
        Role::create(['name' => 'QA Manager']);
        Role::create(['name' => 'QA Staff']);
        Role::create(['name' => 'Security']);
        Role::firstOrCreate(['name' => 'QA Supervisor']);
        Role::firstOrCreate(['name' => 'Supply Chain Administration System Staff']);
        Role::firstOrCreate(['name' => 'Warehouse Manager']);
    }
}
