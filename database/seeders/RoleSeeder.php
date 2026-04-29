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
            'notes',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(
                [
                    'role_id' => $superAdmin->id,
                    'permission' => $permission,
                ]
            );
        }

        // Optional standard roles (no sample users are created for these).
        $teacher = Role::firstOrCreate(
            ['slug' => 'teacher'],
            [
                'name' => 'Teacher',
                'description' => 'Can manage modules, students, timetables, and question bank',
                'is_super_admin' => false,
            ]
        );

        $teacherPermissions = [
            'dashboard',
            'attendance',
            'modules',
            'students',
            'timetables',
            'questionbank',
            'photo_library',
        ];

        foreach ($teacherPermissions as $permission) {
            Permission::firstOrCreate([
                'role_id' => $teacher->id,
                'permission' => $permission,
            ]);
        }

        $accountant = Role::firstOrCreate(
            ['slug' => 'accountant'],
            [
                'name' => 'Accountant',
                'description' => 'Can manage payments, salary/payroll and reports',
                'is_super_admin' => false,
            ]
        );

        $accountantPermissions = [
            'dashboard',
            'payments',
            'salary_payroll',
            'reports',
        ];

        foreach ($accountantPermissions as $permission) {
            Permission::firstOrCreate([
                'role_id' => $accountant->id,
                'permission' => $permission,
            ]);
        }
    }
}
