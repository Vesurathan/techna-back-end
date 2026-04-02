<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $query = Timetable::with(['slots.module', 'slots.staff', 'slots.classroomRelation']);

        // Filter by batch if provided
        if ($request->has('batch') && $request->batch) {
            $query->where('batch', $request->batch);
        }

        // Filter by date if provided
        if ($request->has('date') && $request->date) {
            $query->whereDate('date', $request->date);
        }

        // Filter by date range if provided
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $page = (int) $request->get('page', 1);
        $perPage = 10; // Maximum 10 records per page

        $timetables = $query->orderBy('created_at', 'desc')
            ->orderBy('date', 'desc')
            ->orderBy('batch', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'timetables' => $timetables->map(function ($timetable) {
                return $this->formatTimetable($timetable);
            }),
            'pagination' => [
                'current_page' => $timetables->currentPage(),
                'per_page' => $timetables->perPage(),
                'total' => $timetables->total(),
                'last_page' => $timetables->lastPage(),
                'from' => $timetables->firstItem(),
                'to' => $timetables->lastItem(),
                'has_more_pages' => $timetables->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch' => 'required|string|max:50',
            'date' => 'required|date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.module_id' => 'required|integer|exists:modules,id',
            'slots.*.staff_id' => 'required|integer|exists:staffs,id',
            'slots.*.classroom_id' => 'nullable|integer|exists:classrooms,id',
            'slots.*.classroom' => 'required_without:slots.*.classroom_id|string|max:100',
            'slots.*.interval_time' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Get weekday from date
            $date = \Carbon\Carbon::parse($validated['date']);
            $weekday = $date->format('l'); // Monday, Tuesday, etc.

            // Check if timetable already exists for this batch and date
            $timetable = Timetable::where('batch', $validated['batch'])
                ->whereDate('date', $validated['date'])
                ->first();

            if ($timetable) {
                // Update existing timetable - delete old slots and create new ones
                $timetable->slots()->delete();
            } else {
                // Create new timetable
                $timetable = Timetable::create([
                    'batch' => $validated['batch'],
                    'date' => $validated['date'],
                    'weekday' => $weekday,
                ]);
            }

            // Create timetable slots
            foreach ($validated['slots'] as $slotData) {
                // If classroom_id is provided, use it; otherwise use classroom string
                $classroomName = null;
                if (isset($slotData['classroom_id']) && $slotData['classroom_id']) {
                    $classroom = Classroom::find($slotData['classroom_id']);
                    $classroomName = $classroom ? $classroom->name : $slotData['classroom'] ?? null;
                } else {
                    $classroomName = $slotData['classroom'] ?? null;
                }

                TimetableSlot::create([
                    'timetable_id' => $timetable->id,
                    'start_time' => $slotData['start_time'],
                    'end_time' => $slotData['end_time'],
                    'module_id' => $slotData['module_id'],
                    'staff_id' => $slotData['staff_id'],
                    'classroom_id' => $slotData['classroom_id'] ?? null,
                    'classroom' => $classroomName,
                    'interval_time' => $slotData['interval_time'] ?? null,
                ]);
            }

            $timetable->load(['slots.module', 'slots.staff']);

            DB::commit();

            return response()->json([
                'timetable' => $this->formatTimetable($timetable),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create timetable: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Timetable $timetable)
    {
        $timetable->load(['slots.module', 'slots.staff', 'slots.classroomRelation']);
        return response()->json([
            'timetable' => $this->formatTimetable($timetable),
        ]);
    }

    public function update(Request $request, Timetable $timetable)
    {
        $validated = $request->validate([
            'batch' => 'required|string|max:50',
            'date' => 'required|date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.module_id' => 'required|integer|exists:modules,id',
            'slots.*.staff_id' => 'required|integer|exists:staffs,id',
            'slots.*.classroom_id' => 'nullable|integer|exists:classrooms,id',
            'slots.*.classroom' => 'required_without:slots.*.classroom_id|string|max:100',
            'slots.*.interval_time' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Get weekday from date
            $date = \Carbon\Carbon::parse($validated['date']);
            $weekday = $date->format('l');

            // Update timetable
            $timetable->update([
                'batch' => $validated['batch'],
                'date' => $validated['date'],
                'weekday' => $weekday,
            ]);

            // Delete existing slots
            $timetable->slots()->delete();

            // Create new slots
            foreach ($validated['slots'] as $slotData) {
                // If classroom_id is provided, use it; otherwise use classroom string
                $classroomName = null;
                if (isset($slotData['classroom_id']) && $slotData['classroom_id']) {
                    $classroom = Classroom::find($slotData['classroom_id']);
                    $classroomName = $classroom ? $classroom->name : $slotData['classroom'] ?? null;
                } else {
                    $classroomName = $slotData['classroom'] ?? null;
                }

                TimetableSlot::create([
                    'timetable_id' => $timetable->id,
                    'start_time' => $slotData['start_time'],
                    'end_time' => $slotData['end_time'],
                    'module_id' => $slotData['module_id'],
                    'staff_id' => $slotData['staff_id'],
                    'classroom_id' => $slotData['classroom_id'] ?? null,
                    'classroom' => $classroomName,
                    'interval_time' => $slotData['interval_time'] ?? null,
                ]);
            }

            $timetable->load(['slots.module', 'slots.staff']);

            DB::commit();

            return response()->json([
                'timetable' => $this->formatTimetable($timetable),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update timetable: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Timetable $timetable)
    {
        $timetable->delete();

        return response()->json(['message' => 'Timetable deleted successfully']);
    }

    public function download(Timetable $timetable)
    {
        $timetable->load(['slots.module', 'slots.staff']);

        // For now, return JSON. In production, you would generate an image here
        // using libraries like Intervention Image or similar
        return response()->json([
            'message' => 'Image download functionality will be implemented',
            'timetable' => $this->formatTimetable($timetable),
        ]);
    }

    private function formatTimetable(Timetable $timetable): array
    {
        return [
            'id' => $timetable->id,
            'batch' => $timetable->batch,
            'date' => $timetable->date->format('Y-m-d'),
            'weekday' => $timetable->weekday,
            'slots' => $timetable->slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'module' => [
                        'id' => $slot->module->id,
                        'name' => $slot->module->name,
                        'category' => $slot->module->category,
                    ],
                    'staff' => [
                        'id' => $slot->staff->id,
                        'name' => $slot->staff->full_name,
                    ],
                    'classroom_id' => $slot->classroom_id,
                    'classroom' => $slot->classroomRelation ? $slot->classroomRelation->name : $slot->classroom,
                    'interval_time' => $slot->interval_time,
                ];
            })->sortBy('start_time')->values(),
            'created_at' => $timetable->created_at->toISOString(),
            'updated_at' => $timetable->updated_at->toISOString(),
        ];
    }
}
