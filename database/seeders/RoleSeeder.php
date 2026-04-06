<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Has full access to all features',
                'is_super_admin' => true,
            ]
        );

        $allPermissions = [
            'dashboard',
            'attendance',
            'modules',
            'students',
            'staffs',
            'timetables',
            'questionbank',
            'payments',
            'salary_payroll',
            'reports',
            'role',
            'photo_library',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(
                [
                    'role_id' => $superAdmin->id,
                    'permission' => $permission,
                ]
            );
        }
    }
}
