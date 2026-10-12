<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'SCM', 'name' => 'Supply Chain Management'],
            ['code' => 'MBB', 'name' => 'Marketing Blood Bank'],
            ['code' => 'MHO', 'name' => 'Marketing Hospital'],
            ['code' => 'MPH', 'name' => 'Marketing Primary Health'],
            ['code' => 'TSS', 'name' => 'Technical Support Services'],
            ['code' => 'MMG', 'name' => 'Medquest Mitra Global'],
            ['code' => 'CAL', 'name' => 'Calibration'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['code' => $department['code']], $department);
        }
    }
}
