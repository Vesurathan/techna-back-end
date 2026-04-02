<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'isSuperAdmin' => $role->is_super_admin,
                    'permissions' => $role->permissions->pluck('permission')->toArray(),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:dashboard,attendance,modules,students,staffs,timetables,questionbank,payments,salary_payroll,reports,role,photo_library',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_super_admin' => false,
        ]);

        foreach ($validated['permissions'] as $permission) {
            Permission::create([
                'role_id' => $role->id,
                'permission' => $permission,
            ]);
        }

        $role->load('permissions');

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'isSuperAdmin' => $role->is_super_admin,
                'permissions' => $role->permissions->pluck('permission')->toArray(),
            ],
        ], 201);
    }

    public function show(Role $role)
    {
        $role->load('permissions');

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'isSuperAdmin' => $role->is_super_admin,
                'permissions' => $role->permissions->pluck('permission')->toArray(),
            ],
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->is_super_admin) {
            return response()->json([
                'message' => 'Cannot modify Super Admin role',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($role->id),
            ],
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:dashboard,attendance,modules,students,staffs,timetables,questionbank,payments,salary_payroll,reports,role,photo_library',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        // Delete existing permissions
        $role->permissions()->delete();

        // Create new permissions
        foreach ($validated['permissions'] as $permission) {
            Permission::create([
                'role_id' => $role->id,
                'permission' => $permission,
            ]);
        }

        $role->load('permissions');

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'isSuperAdmin' => $role->is_super_admin,
                'permissions' => $role->permissions->pluck('permission')->toArray(),
            ],
        ]);
    }

    public function destroy(Role $role)
    {
        if ($role->is_super_admin) {
            return response()->json([
                'message' => 'Cannot delete Super Admin role',
            ], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
