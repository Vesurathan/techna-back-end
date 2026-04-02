<?php

namespace Database\Seeders;

use App\Models\Classroom;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classrooms = [
            [
                'name' => 'Hall A',
                'type' => 'hall',
                'capacity' => 100,
                'description' => 'Main lecture hall with audio-visual equipment',
                'is_active' => true,
            ],
            [
                'name' => 'Hall B',
                'type' => 'hall',
                'capacity' => 80,
                'description' => 'Secondary lecture hall',
                'is_active' => true,
            ],
            [
                'name' => 'Room 101',
                'type' => 'classroom',
                'capacity' => 30,
                'description' => 'Standard classroom',
                'is_active' => true,
            ],
            [
                'name' => 'Room 102',
                'type' => 'classroom',
                'capacity' => 30,
                'description' => 'Standard classroom',
                'is_active' => true,
            ],
            [
                'name' => 'Room 201',
                'type' => 'classroom',
                'capacity' => 25,
                'description' => 'Small classroom for tutorials',
                'is_active' => true,
            ],
            [
                'name' => 'Lab 1',
                'type' => 'lab',
                'capacity' => 20,
                'description' => 'Computer lab with 20 workstations',
                'is_active' => true,
            ],
            [
                'name' => 'Lab 2',
                'type' => 'lab',
                'capacity' => 20,
                'description' => 'Electronics lab',
                'is_active' => true,
            ],
            [
                'name' => 'Auditorium',
                'type' => 'auditorium',
                'capacity' => 200,
                'description' => 'Main auditorium for large events and lectures',
                'is_active' => true,
            ],
        ];

        foreach ($classrooms as $classroom) {
            Classroom::create($classroom);
        }
    }
}
