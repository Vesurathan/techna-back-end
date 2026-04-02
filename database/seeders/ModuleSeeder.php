<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Engineering Science Innovation',
                'category' => 'main',
                'sub_modules_count' => 5,
                'amount' => 15000.00,
                'staff_ids' => [1, 2], // Sarah Johnson, David Lee
            ],
            [
                'name' => 'Mathematics Fundamentals',
                'category' => 'compulsory',
                'sub_modules_count' => 3,
                'amount' => 12000.00,
                'staff_ids' => [2], // David Lee
            ],
            [
                'name' => 'Computer Programming',
                'category' => 'main',
                'sub_modules_count' => 4,
                'amount' => 18000.00,
                'staff_ids' => [1, 3], // Sarah Johnson, Priya Nair
            ],
            [
                'name' => 'Electronics and Circuits',
                'category' => 'main',
                'sub_modules_count' => 4,
                'amount' => 16000.00,
                'staff_ids' => [3], // Priya Nair
            ],
            [
                'name' => 'Mechanical Systems',
                'category' => 'main',
                'sub_modules_count' => 3,
                'amount' => 14000.00,
                'staff_ids' => [4], // Ahmed Khan
            ],
            [
                'name' => 'Business Management',
                'category' => 'basket',
                'sub_modules_count' => 2,
                'amount' => 10000.00,
                'staff_ids' => [5], // Maria Garcia
            ],
            [
                'name' => 'Data Structures and Algorithms',
                'category' => 'compulsory',
                'sub_modules_count' => 3,
                'amount' => 15000.00,
                'staff_ids' => [1], // Sarah Johnson
            ],
            [
                'name' => 'Digital Systems Design',
                'category' => 'main',
                'sub_modules_count' => 3,
                'amount' => 13000.00,
                'staff_ids' => [3], // Priya Nair
            ],
        ];

        foreach ($modules as $moduleData) {
            $staffIds = $moduleData['staff_ids'];
            unset($moduleData['staff_ids']);

            $module = Module::create($moduleData);

            // Attach staff to module
            if (!empty($staffIds)) {
                $module->staffs()->attach($staffIds);
            }
        }
    }
}
