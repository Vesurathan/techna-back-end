<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $query = Classroom::query();

        // Filter by active status if provided
        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        // Filter by type if provided
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $classrooms = $query->orderBy('created_at', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'classrooms' => $classrooms->map(function ($classroom) {
                return $this->formatClassroom($classroom);
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:classrooms,name',
            'type' => 'nullable|string|max:50|in:classroom,hall,lab,auditorium,other',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $classroom = Classroom::create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'classroom',
            'capacity' => $validated['capacity'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'classroom' => $this->formatClassroom($classroom),
        ], 201);
    }

    public function show(Classroom $classroom)
    {
        return response()->json([
            'classroom' => $this->formatClassroom($classroom),
        ]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:classrooms,name,' . $classroom->id,
            'type' => 'nullable|string|max:50|in:classroom,hall,lab,auditorium,other',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $classroom->update($validated);

        return response()->json([
            'classroom' => $this->formatClassroom($classroom),
        ]);
    }

    public function destroy(Classroom $classroom)
    {
        // Check if classroom is being used in any timetable slots
        if ($classroom->timetableSlots()->exists()) {
            return response()->json([
                'message' => 'Cannot delete classroom that is assigned to timetable slots',
            ], 422);
        }

        $classroom->delete();

        return response()->json(['message' => 'Classroom deleted successfully']);
    }

    private function formatClassroom(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'type' => $classroom->type,
            'capacity' => $classroom->capacity,
            'description' => $classroom->description,
            'is_active' => $classroom->is_active,
            'created_at' => $classroom->created_at->toISOString(),
            'updated_at' => $classroom->updated_at->toISOString(),
        ];
    }
}
