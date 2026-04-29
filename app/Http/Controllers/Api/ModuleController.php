<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\SubModule;
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
            'subModules',
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
            'sub_modules' => 'nullable|array',
            'sub_modules.*.name' => 'required|string|max:255',
            'sub_modules.*.sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        $subModules = $validated['sub_modules'] ?? [];
        if (is_array($subModules) && count($subModules) > 0) {
            $validated['sub_modules_count'] = count($subModules);
        }

        $module = Module::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'sub_modules_count' => $validated['sub_modules_count'],
            'amount' => $validated['amount'],
        ]);

        if (!empty($validated['staff_ids'])) {
            $module->staffs()->sync($validated['staff_ids']);
        }

        if (!empty($subModules)) {
            $rows = array_map(function ($row) use ($module) {
                return [
                    'module_id' => $module->id,
                    'name' => $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $subModules);
            SubModule::insert($rows);
        }

        $module->load([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
            'subModules',
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
            'subModules',
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
            'sub_modules' => 'nullable|array',
            'sub_modules.*.id' => 'nullable|integer|exists:sub_modules,id',
            'sub_modules.*.name' => 'required|string|max:255',
            'sub_modules.*.sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        $subModules = $validated['sub_modules'] ?? [];
        if (is_array($subModules) && count($subModules) > 0) {
            $validated['sub_modules_count'] = count($subModules);
        }

        $module->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'sub_modules_count' => $validated['sub_modules_count'],
            'amount' => $validated['amount'],
        ]);

        $module->staffs()->sync($validated['staff_ids'] ?? []);

        // Sync sub modules (create/update/delete)
        if (is_array($subModules)) {
            $existing = $module->subModules()->get()->keyBy('id');
            $keepIds = [];

            foreach ($subModules as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : null;
                if ($id && $existing->has($id)) {
                    $sm = $existing->get($id);
                    $sm->update([
                        'name' => $row['name'],
                        'sort_order' => (int) ($row['sort_order'] ?? 0),
                    ]);
                    $keepIds[] = $id;
                    continue;
                }

                $created = $module->subModules()->create([
                    'name' => $row['name'],
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ]);
                $keepIds[] = $created->id;
            }

            $module->subModules()->whereNotIn('id', $keepIds)->delete();
        }

        $module->load([
            'staffs' => function ($query) {
                $query->where('status', 'active');
            },
            'subModules',
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
            'sub_modules' => $module->subModules->map(fn ($sm) => [
                'id' => (string) $sm->id,
                'name' => $sm->name,
                'sort_order' => (int) $sm->sort_order,
            ])->values()->all(),
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
