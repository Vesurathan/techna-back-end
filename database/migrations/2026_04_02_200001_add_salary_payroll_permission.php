<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super-admin', 'accountant'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permissions')
                ->where('role_id', $roleId)
                ->where('permission', 'salary_payroll')
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'role_id' => $roleId,
                    'permission' => 'salary_payroll',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('permission', 'salary_payroll')->delete();
    }
};
