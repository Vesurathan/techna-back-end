<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = 10; // Maximum 10 records per page

        $modules = Module::with([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
        ])
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'modules' => $modules->map(function ($module) {
                return $this->formatModule($module);
            }),
            'pagination' => [
                'current_page' => $modules->currentPage(),
                'per_page' => $modules->perPage(),
                'total' => $modules->total(),
                'last_page' => $modules->lastPage(),
                'from' => $modules->firstItem(),
                'to' => $modules->lastItem(),
                'has_more_pages' => $modules->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(['main', 'compulsory', 'basket'])],
            'sub_modules_count' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:staffs,id',
        ]);

        $module = Module::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'sub_modules_count' => $validated['sub_modules_count'],
            'amount' => $validated['amount'],
        ]);

        if (!empty($validated['staff_ids'])) {
            $module->staffs()->sync($validated['staff_ids']);
        }

        $module->load([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
        ]);

        return response()->json([
            'module' => $this->formatModule($module),
        ], 201);
    }

    public function show(Module $module)
    {
        $module->load([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
        ]);

        return response()->json([
            'module' => $this->formatModule($module),
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(['main', 'compulsory', 'basket'])],
            'sub_modules_count' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:staffs,id',
        ]);

        $module->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'sub_modules_count' => $validated['sub_modules_count'],
            'amount' => $validated['amount'],
        ]);

        $module->staffs()->sync($validated['staff_ids'] ?? []);

        $module->load([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
        ]);

        return response()->json([
            'module' => $this->formatModule($module),
        ]);
    }

    public function destroy(Module $module)
    {
        $module->staffs()->detach();
        $module->delete();

        return response()->json(['message' => 'Module deleted successfully']);
    }

    private function formatModule(Module $module): array
    {
        return [
            'id' => $module->id,
            'name' => $module->name,
            'category' => $module->category,
            'sub_modules_count' => $module->sub_modules_count,
            'amount' => $module->amount,
            'staffs' => $module->staffs->map(function ($staff) {
                return [
                    'id' => $staff->id,
                    'first_name' => $staff->first_name,
                    'last_name' => $staff->last_name,
                    'full_name' => $staff->full_name ?? "{$staff->first_name} {$staff->last_name}",
                    'email' => null, // Staff no longer has email field
                    'department' => null, // Staff no longer has department field
                ];
            }),
        ];
    }
}
