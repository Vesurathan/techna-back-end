<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = Module::all();

        if ($modules->isEmpty()) {
            $this->command->warn('No modules found. Please seed modules first.');
            return;
        }

        $firstNames = [
            'Kamal', 'Nimal', 'Sunil', 'Priya', 'Sachini', 'Dilshan', 'Tharindu', 'Chamara',
            'Nadeesha', 'Sanduni', 'Hasitha', 'Kavindu', 'Dilani', 'Sajani', 'Ravindu',
            'Pasindu', 'Dinuka', 'Nipuni', 'Chathura', 'Ishara', 'Prabodha', 'Sachith',
            'Tharuka', 'Dilhara', 'Nimesha', 'Chaminda', 'Nuwan', 'Sampath', 'Kasun',
            'Dinesh', 'Chathurika', 'Nirosha', 'Shashika', 'Tharushi', 'Dilmi', 'Nethmi'
        ];

        $lastNames = [
            'Perera', 'Fernando', 'Silva', 'De Silva', 'Wickramasinghe', 'Jayasinghe',
            'Bandara', 'Jayawardena', 'Ratnayake', 'Gunasekara', 'Weerasinghe',
            'Abeysekara', 'Karunaratne', 'Dissanayake', 'Wijesinghe', 'Mendis',
            'Amarasinghe', 'Rajapaksa', 'Premadasa', 'Wickremesinghe', 'Kumarasinghe',
            'Gunawardena', 'Herath', 'Pathirana', 'Samarasinghe', 'Wijeratne',
            'Gamage', 'Senanayake', 'Alwis', 'Cooray', 'Peiris', 'Goonetilleke'
        ];

        $schools = [
            'Royal College', 'Ananda College', 'Nalanda College', 'Dharmaraja College',
            'Trinity College', 'St. Joseph\'s College', 'St. Peter\'s College',
            'Vishaka Vidyalaya', 'Musaeus College', 'Ladies\' College',
            'Mahamaya Girls\' College', 'Rathnavali Balika Vidyalaya',
            'Dharmapala Vidyalaya', 'Mahanama College', 'Isipathana College'
        ];

        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $genders = ['male', 'female', 'other'];
        $batches = ['2024', '2025', '2026'];
        $paymentTypes = ['full', 'admission_only'];
        $statuses = ['active', 'active', 'active', 'active', 'inactive']; // Mostly active

        $addresses = [
            '123 Main Street, Colombo 05',
            '456 Galle Road, Mount Lavinia',
            '789 Kandy Road, Kandy',
            '321 Negombo Road, Negombo',
            '654 Matara Road, Matara',
            '987 Kurunegala Road, Kurunegala',
            '147 Anuradhapura Road, Anuradhapura',
            '258 Gampaha Road, Gampaha',
            '369 Ratnapura Road, Ratnapura',
            '741 Kalutara Road, Kalutara'
        ];

        // Generate 30 sample students
        for ($i = 1; $i <= 30; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $gender = $genders[array_rand($genders)];
            
            // Select random modules (1-3 modules)
            $selectedModules = $modules->random(rand(1, min(3, $modules->count())));
            $moduleIds = $selectedModules->pluck('id')->toArray();
            
            // Generate admission number (format: {batchDigits}|{moduleLetters}|{genderLetter}|{uniqueNumber})
            $batch = $batches[array_rand($batches)];
            $batchDigits = substr($batch, -2);
            
            // Get first letters of selected modules
            $moduleLetters = $selectedModules->sortBy('name')->map(function ($module) {
                return strtoupper(substr($module->name, 0, 1));
            })->implode('');
            
            // Get gender letter
            $genderLetter = match($gender) {
                'male' => 'M',
                'female' => 'F',
                'other' => 'O',
                default => 'M',
            };
            
            // Generate unique number
            $uniqueNumber = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $admissionNumber = "{$batchDigits}|{$moduleLetters}|{$genderLetter}|{$uniqueNumber}";
            
            // Ensure uniqueness
            $attempts = 0;
            while (Student::where('admission_number', $admissionNumber)->exists() && $attempts < 10) {
                $uniqueNumber = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $admissionNumber = "{$batchDigits}|{$moduleLetters}|{$genderLetter}|{$uniqueNumber}";
                $attempts++;
            }
            
            // Generate barcode based on admission number
            $timestamp = time() + $i; // Add $i to ensure uniqueness
            $hash = substr(md5($admissionNumber . $timestamp), 0, 8);
            $barcode = strtoupper($hash);
            
            // Ensure barcode uniqueness
            $barcodeAttempts = 0;
            while (Student::where('barcode', $barcode)->exists() && $barcodeAttempts < 10) {
                $timestamp = time() + $i + $barcodeAttempts;
                $hash = substr(md5($admissionNumber . $timestamp), 0, 8);
                $barcode = strtoupper($hash);
                $barcodeAttempts++;
            }
            
            // Generate date of birth (between 15-18 years ago)
            $yearsAgo = rand(15, 18);
            $dateOfBirth = Carbon::now()->subYears($yearsAgo)->subMonths(rand(0, 11))->subDays(rand(0, 28));
            
            // Generate NIC (if 18+)
            $nicNumber = null;
            if ($yearsAgo >= 18) {
                $nicNumber = sprintf('%09dV', rand(100000000, 999999999));
            }
            
            // Generate phone numbers
            $personalPhone = '07' . rand(10000000, 99999999);
            $parentPhone = '07' . rand(10000000, 99999999);
            
            // Calculate module total amount
            $moduleTotalAmount = $selectedModules->sum('amount');
            
            // Determine payment type and paid amount
            $paymentType = $paymentTypes[array_rand($paymentTypes)];
            $paidAmount = 500.00; // Admission fee
            if ($paymentType === 'full') {
                $paidAmount += $moduleTotalAmount;
            }
            
            $status = $statuses[array_rand($statuses)];

            $student = Student::create([
                'admission_number' => $admissionNumber,
                'barcode' => $barcode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
                'nic_number' => $nicNumber,
                'personal_phone' => $personalPhone,
                'parent_phone' => $parentPhone,
                'personal_phone_has_whatsapp' => rand(0, 1) === 1,
                'parent_phone_has_whatsapp' => rand(0, 1) === 1,
                'admission_batch' => $batch,
                'address' => $addresses[array_rand($addresses)],
                'school_name' => $schools[array_rand($schools)],
                'blood_group' => $bloodGroups[array_rand($bloodGroups)],
                'medical_notes' => rand(0, 10) > 8 ? 'No known allergies' : null,
                'image_path' => null, // Can be added later if needed
                'admission_fee' => 500.00,
                'module_total_amount' => $moduleTotalAmount,
                'paid_amount' => $paidAmount,
                'payment_type' => $paymentType,
                'status' => $status,
            ]);

            // Attach modules
            $student->modules()->sync($moduleIds);
        }

        $this->command->info('Created 30 sample students with modules.');
    }
}
