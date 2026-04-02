<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Student;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::with('modules')->get();
        $modules = Module::all();

        if ($students->isEmpty()) {
            $this->command->warn('No students found. Please seed students first.');
            return;
        }

        $paymentMethods = ['cash', 'card'];
        $statuses = ['paid', 'partial', 'pending'];

        // Generate payments for last 6 months
        for ($monthOffset = 0; $monthOffset < 6; $monthOffset++) {
            $date = Carbon::now()->subMonths($monthOffset);
            $month = $date->format('Y-m');
            $year = $date->year;

            foreach ($students as $student) {
                // Skip some students randomly (not all pay every month)
                if (rand(1, 10) > 7) {
                    continue;
                }

                // Calculate monthly fee based on enrolled modules
                $monthlyFee = 0;
                foreach ($student->modules as $module) {
                    $moduleFee = DB::table('module_fees')
                        ->where('module_id', $module->id)
                        ->where('effective_from', '<=', $date)
                        ->where(function ($q) use ($date) {
                            $q->whereNull('effective_to')
                              ->orWhere('effective_to', '>=', $date);
                        })
                        ->orderBy('effective_from', 'desc')
                        ->first();

                    if ($moduleFee) {
                        $monthlyFee += $moduleFee->monthly_fee;
                    } else {
                        $monthlyFee += $module->amount ?? 5000;
                    }
                }

                if ($monthlyFee <= 0) {
                    continue;
                }

                // Random discount (10% chance of discount)
                $discountAmount = 0;
                if (rand(1, 10) === 1) {
                    $discountAmount = round($monthlyFee * (rand(5, 20) / 100), 2);
                }

                $amountAfterDiscount = $monthlyFee - $discountAmount;

                // Determine payment status
                $status = $statuses[array_rand($statuses)];
                $paidAmount = 0;

                if ($status === 'paid') {
                    $paidAmount = $amountAfterDiscount;
                } elseif ($status === 'partial') {
                    $paidAmount = round($amountAfterDiscount * (rand(30, 80) / 100), 2);
                } else {
                    $paidAmount = 0;
                }

                // Random payment date within the month
                $paymentDate = $date->copy()->day(rand(1, min(28, $date->daysInMonth)));

                // Random module (or null for all modules)
                $moduleId = null;
                if (rand(1, 2) === 1 && $student->modules->isNotEmpty()) {
                    $moduleId = $student->modules->random()->id;
                }

                Payment::create([
                    'student_id' => $student->id,
                    'module_id' => $moduleId,
                    'amount' => $monthlyFee,
                    'discount_amount' => $discountAmount,
                    'paid_amount' => $paidAmount,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'payment_date' => $paymentDate,
                    'month' => $month,
                    'year' => $year,
                    'status' => $status,
                    'notes' => $discountAmount > 0 ? 'Discount applied due to financial hardship' : null,
                    'receipt_number' => $this->generateReceiptNumber($year, $date->format('m')),
                    'created_by' => 1, // Assuming user ID 1 exists
                ]);

                // Update student's paid_amount
                $student->increment('paid_amount', $paidAmount);
            }
        }
    }

    private function generateReceiptNumber($year, $month): string
    {
        static $sequence = [];
        $key = "{$year}{$month}";
        
        if (!isset($sequence[$key])) {
            $sequence[$key] = 0;
        }
        
        $sequence[$key]++;
        
        return sprintf('RCP-%s%s-%04d', $year, $month, $sequence[$key]);
    }
}
