<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleFee;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ModuleFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = Module::all();

        foreach ($modules as $module) {
            // Create current fee structure
            ModuleFee::create([
                'module_id' => $module->id,
                'monthly_fee' => $module->amount ?? 5000.00, // Use module amount or default
                'effective_from' => Carbon::now()->startOfYear(),
                'effective_to' => null, // Current fee
                'description' => 'Standard monthly fee for ' . $module->name,
            ]);

            // Create previous year fee structure (for historical data)
            ModuleFee::create([
                'module_id' => $module->id,
                'monthly_fee' => ($module->amount ?? 5000.00) * 0.95, // 5% less than current
                'effective_from' => Carbon::now()->subYear()->startOfYear(),
                'effective_to' => Carbon::now()->startOfYear()->subDay(),
                'description' => 'Previous year fee structure for ' . $module->name,
            ]);
        }
    }
}
