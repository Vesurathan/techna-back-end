<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if ($superAdminRole) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'admin@techna.edu',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
            ]);
        }
    }
}
