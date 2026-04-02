<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staffs = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'nic_number' => '123456789V',
                'date_of_birth' => '1980-05-15',
                'address' => '123 Main Street, Colombo',
                'gender' => 'female',
                'blood_group' => 'A+',
                'school_name' => 'University of Colombo',
                'qualifications' => 'Ph.D. in Computer Science, M.Sc. in Software Engineering',
                'secondary_phone' => '+94 77 123 4567',
                'secondary_phone_has_whatsapp' => true,
                'medical_notes' => null,
                'status' => 'active',
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Lee',
                'nic_number' => '234567890V',
                'date_of_birth' => '1985-08-20',
                'address' => '456 Oak Avenue, Kandy',
                'gender' => 'male',
                'blood_group' => 'B+',
                'school_name' => 'University of Peradeniya',
                'qualifications' => 'M.Sc. in Mathematics, B.Sc. in Applied Mathematics',
                'secondary_phone' => '+94 77 234 5678',
                'secondary_phone_has_whatsapp' => true,
                'medical_notes' => null,
                'status' => 'active',
            ],
            [
                'first_name' => 'Priya',
                'last_name' => 'Nair',
                'nic_number' => '345678901V',
                'date_of_birth' => '1982-11-10',
                'address' => '789 Pine Road, Galle',
                'gender' => 'female',
                'blood_group' => 'O+',
                'school_name' => 'University of Moratuwa',
                'qualifications' => 'B.Eng. in Electronics, M.Eng. in Telecommunications',
                'secondary_phone' => '+94 77 345 6789',
                'secondary_phone_has_whatsapp' => true,
                'medical_notes' => null,
                'status' => 'active',
            ],
            [
                'first_name' => 'Ahmed',
                'last_name' => 'Khan',
                'nic_number' => '456789012V',
                'date_of_birth' => '1983-03-25',
                'address' => '321 Elm Street, Jaffna',
                'gender' => 'male',
                'blood_group' => 'AB+',
                'school_name' => 'University of Jaffna',
                'qualifications' => 'B.Eng. in Mechanical Engineering, M.Eng. in Industrial Engineering',
                'secondary_phone' => '+94 77 456 7890',
                'secondary_phone_has_whatsapp' => false,
                'medical_notes' => null,
                'status' => 'active',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Garcia',
                'nic_number' => '567890123V',
                'date_of_birth' => '1987-07-30',
                'address' => '654 Maple Drive, Negombo',
                'gender' => 'female',
                'blood_group' => 'A-',
                'school_name' => 'University of Kelaniya',
                'qualifications' => 'MBA in Business Administration, B.Com. in Accounting',
                'secondary_phone' => '+94 77 567 8901',
                'secondary_phone_has_whatsapp' => true,
                'medical_notes' => null,
                'status' => 'active',
            ],
        ];

        foreach ($staffs as $staff) {
            Staff::create($staff);
        }
    }
}
