<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin role
        $superAdmin = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Has full access to all features',
            'is_super_admin' => true,
        ]);

        // Add all permissions to Super Admin
        $allPermissions = [
            'dashboard',
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
            Permission::create([
                'role_id' => $superAdmin->id,
                'permission' => $permission,
            ]);
        }

        // Create Teacher role
        $teacher = Role::create([
            'name' => 'Teacher',
            'slug' => 'teacher',
            'description' => 'Can manage modules, students, and timetables',
            'is_super_admin' => false,
        ]);

        $teacherPermissions = [
            'dashboard',
            'modules',
            'students',
            'timetables',
            'questionbank',
            'photo_library',
        ];

        foreach ($teacherPermissions as $permission) {
            Permission::create([
                'role_id' => $teacher->id,
                'permission' => $permission,
            ]);
        }

        // Create Accountant role
        $accountant = Role::create([
            'name' => 'Accountant',
            'slug' => 'accountant',
            'description' => 'Can manage payments and reports',
            'is_super_admin' => false,
        ]);

        $accountantPermissions = [
            'dashboard',
            'payments',
            'salary_payroll',
            'reports',
        ];

        foreach ($accountantPermissions as $permission) {
            Permission::create([
                'role_id' => $accountant->id,
                'permission' => $permission,
            ]);
        }
    }
}
