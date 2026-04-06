<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if (! $superAdminRole) {
            return;
        }

        $email = env('SEED_SUPER_ADMIN_EMAIL', 'admin@techna.edu');
        $password = env('SEED_SUPER_ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'role_id' => $superAdminRole->id,
            ]
        );
    }
}
