<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();
        $staffs = Staff::all();

        // Generate attendance for last 7 days
        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = Carbon::now()->subDays($dayOffset);

            // Student attendance
            foreach ($students as $student) {
                // Skip some students randomly (not all attend every day)
                if (rand(1, 10) > 8) {
                    continue;
                }

                $timeIn = Carbon::createFromTime(rand(7, 9), rand(0, 59)); // 7:00 AM to 9:59 AM
                $timeOut = Carbon::createFromTime(rand(15, 17), rand(0, 59)); // 3:00 PM to 5:59 PM

                // Determine status
                $status = 'present';
                if ($timeIn->gt(Carbon::createFromTime(8, 15))) {
                    $status = 'late';
                }

                Attendance::create([
                    'type' => 'student',
                    'student_id' => $student->id,
                    'staff_id' => null,
                    'date' => $date,
                    'time_in' => $timeIn->format('H:i'),
                    'time_out' => $timeOut->format('H:i'),
                    'status' => $status,
                    'barcode' => $student->barcode,
                ]);
            }

            // Staff attendance
            foreach ($staffs as $staff) {
                // Skip some staff randomly
                if (rand(1, 10) > 7) {
                    continue;
                }

                if (!$staff->barcode) {
                    continue; // Skip staff without barcode
                }

                $timeIn = Carbon::createFromTime(rand(7, 8), rand(0, 30)); // 7:00 AM to 8:30 AM
                $timeOut = Carbon::createFromTime(rand(16, 18), rand(0, 59)); // 4:00 PM to 6:59 PM

                // Determine status
                $status = 'present';
                if ($timeIn->gt(Carbon::createFromTime(8, 15))) {
                    $status = 'late';
                }

                Attendance::create([
                    'type' => 'staff',
                    'student_id' => null,
                    'staff_id' => $staff->id,
                    'date' => $date,
                    'time_in' => $timeIn->format('H:i'),
                    'time_out' => $timeOut->format('H:i'),
                    'status' => $status,
                    'barcode' => $staff->barcode,
                ]);
            }
        }
    }
}
