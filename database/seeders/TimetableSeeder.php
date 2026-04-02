<?php

namespace Database\Seeders;

use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\Module;
use App\Models\Staff;
use App\Models\Classroom;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TimetableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some modules, staff, and classrooms
        $modules = Module::all();
        $staffs = Staff::all();
        $classrooms = Classroom::all();

        if ($modules->isEmpty() || $staffs->isEmpty() || $classrooms->isEmpty()) {
            $this->command->warn('Please seed modules, staffs, and classrooms first!');
            return;
        }

        // Create timetables for different batches and dates
        $batches = ['2026', '2025', '2024'];
        $startDate = Carbon::now()->startOfWeek(); // Start from this week's Monday

        foreach ($batches as $batchIndex => $batch) {
            // Create timetable for each day of the week
            for ($day = 0; $day < 5; $day++) { // Monday to Friday
                $date = $startDate->copy()->addDays($day);
                $weekday = $date->format('l');

                // Check if timetable already exists
                $existingTimetable = Timetable::where('batch', $batch)
                    ->whereDate('date', $date)
                    ->first();

                if ($existingTimetable) {
                    continue; // Skip if already exists
                }

                $timetable = Timetable::create([
                    'batch' => $batch,
                    'date' => $date,
                    'weekday' => $weekday,
                ]);

                // Create time slots for the day
                $slots = $this->generateTimeSlots($day, $modules, $staffs, $classrooms);

                foreach ($slots as $slotData) {
                    TimetableSlot::create([
                        'timetable_id' => $timetable->id,
                        'start_time' => $slotData['start_time'],
                        'end_time' => $slotData['end_time'],
                        'module_id' => $slotData['module_id'],
                        'staff_id' => $slotData['staff_id'],
                        'classroom_id' => $slotData['classroom_id'],
                        'classroom' => $slotData['classroom'],
                        'interval_time' => $slotData['interval_time'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Generate time slots for a day
     */
    private function generateTimeSlots(int $dayIndex, $modules, $staffs, $classrooms): array
    {
        $slots = [];
        $timeSlots = [
            ['start' => '08:00', 'end' => '09:30'],
            ['start' => '09:45', 'end' => '11:15'],
            ['start' => '11:30', 'end' => '13:00'],
            ['start' => '14:00', 'end' => '15:30'],
            ['start' => '15:45', 'end' => '17:15'],
        ];

        // Select 3-5 random time slots per day
        $numSlots = rand(3, 5);
        $selectedSlots = array_rand($timeSlots, min($numSlots, count($timeSlots)));
        
        if (!is_array($selectedSlots)) {
            $selectedSlots = [$selectedSlots];
        }

        foreach ($selectedSlots as $slotIndex) {
            $timeSlot = $timeSlots[$slotIndex];
            
            // Get random module, staff, and classroom
            $module = $modules->random();
            $staff = $staffs->random();
            $classroom = $classrooms->random();

            // Check if staff is assigned to this module
            $moduleStaffs = $module->staffs;
            if ($moduleStaffs->isNotEmpty()) {
                $staff = $moduleStaffs->random();
            }

            $slots[] = [
                'start_time' => $timeSlot['start'],
                'end_time' => $timeSlot['end'],
                'module_id' => $module->id,
                'staff_id' => $staff->id,
                'classroom_id' => $classroom->id,
                'classroom' => $classroom->name,
                'interval_time' => $slotIndex < count($timeSlots) - 1 ? 15 : null, // 15 min interval between classes
            ];
        }

        // Sort slots by start time
        usort($slots, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        return $slots;
    }
}
