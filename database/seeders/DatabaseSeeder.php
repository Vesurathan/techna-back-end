<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            StaffSeeder::class,
            StaffBarcodeSeeder::class, // Generate barcodes for existing staff
            ClassroomSeeder::class,
            ModuleSeeder::class,
            StudentSeeder::class, // Students must be seeded before payments
            TimetableSeeder::class,
            QuestionSeeder::class,
            QuestionnaireSeeder::class,
            ModuleFeeSeeder::class,
            PaymentSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
