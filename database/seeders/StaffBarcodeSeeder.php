<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffBarcodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staffs = Staff::whereNull('barcode')->get();

        foreach ($staffs as $index => $staff) {
            $timestamp = time() + $index;
            $hash = substr(md5(uniqid(rand(), true) . $timestamp), 0, 8);
            $barcode = 'STF' . strtoupper($hash);
            
            // Ensure uniqueness
            $attempts = 0;
            while (Staff::where('barcode', $barcode)->exists() && $attempts < 10) {
                $timestamp = time() + $index + $attempts;
                $hash = substr(md5(uniqid(rand(), true) . $timestamp), 0, 8);
                $barcode = 'STF' . strtoupper($hash);
                $attempts++;
            }
            
            $staff->update(['barcode' => $barcode]);
        }

        $this->command->info("Generated barcodes for {$staffs->count()} staff members.");
    }
}
