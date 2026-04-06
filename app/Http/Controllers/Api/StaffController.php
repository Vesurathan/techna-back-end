<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MediaDisk;
use App\Models\Staff;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        // Check if 'all' parameter is set to get all staff without pagination
        if ($request->has('all') && $request->get('all') === 'true') {
            $staffs = Staff::with(['modules', 'user.role.permissions'])
                ->where('status', 'active')
                ->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc')
                ->get();

            return response()->json([
                'staffs' => $staffs->map(function ($staff) {
                    return $this->formatStaff($staff);
                }),
            ]);
        }

        $page = (int) $request->get('page', 1);
        $perPage = 10; // Maximum 10 records per page

        $query = Staff::with(['modules', 'user.role.permissions']);

        if (! $request->boolean('include_inactive')) {
            $query->where('status', 'active');
        }

        $staffs = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'staffs' => $staffs->map(function ($staff) {
                return $this->formatStaff($staff);
            }),
            'pagination' => [
                'current_page' => $staffs->currentPage(),
                'per_page' => $staffs->perPage(),
                'total' => $staffs->total(),
                'last_page' => $staffs->lastPage(),
                'from' => $staffs->firstItem(),
                'to' => $staffs->lastItem(),
                'has_more_pages' => $staffs->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nic_number' => 'nullable|string|max:20|unique:staffs,nic_number',
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'gender' => 'required|in:male,female,other',
            'blood_group' => 'nullable|string|max:10',
            'school_name' => 'nullable|string|max:255',
            'qualifications' => 'nullable|string',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer|exists:modules,id',
            'secondary_phone' => 'required|string|max:20',
            'secondary_phone_has_whatsapp' => 'boolean',
            'medical_notes' => 'nullable|string',
            'image_path' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'status' => 'nullable|in:active,inactive,on_leave,terminated',
        ]);

        DB::beginTransaction();
        try {
            // Generate barcode if not provided
            $barcode = $this->generateBarcode();

            $staff = Staff::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'nic_number' => $validated['nic_number'] ?? null,
                'barcode' => $barcode,
                'date_of_birth' => $validated['date_of_birth'],
                'address' => $validated['address'],
                'gender' => $validated['gender'],
                'blood_group' => $validated['blood_group'] ?? null,
                'school_name' => $validated['school_name'] ?? null,
                'qualifications' => $validated['qualifications'] ?? null,
                'secondary_phone' => $validated['secondary_phone'],
                'secondary_phone_has_whatsapp' => $validated['secondary_phone_has_whatsapp'] ?? false,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'image_path' => $validated['image_path'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $staff->modules()->sync($validated['module_ids'] ?? []);

            if ($request->hasFile('image')) {
                $path = MediaDisk::storeUpload($request->file('image'), 'staff/'.$staff->id);
                $staff->image_path = $path;
                $staff->save();
            }

            $staff->load('modules');

            DB::commit();

            return response()->json([
                'staff' => $this->formatStaff($staff->fresh()),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create staff: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Staff $staff)
    {
        $staff->load(['modules', 'user.role.permissions']);
        return response()->json([
            'staff' => $this->formatStaff($staff),
        ]);
    }

    /**
     * Create or update staff login + role (Super Admin only).
     */
    public function setAccess(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($staff->user?->id),
            ],
            'password' => 'nullable|string|min:8',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if ($role->is_super_admin) {
            return response()->json([
                'message' => 'Cannot assign Super Admin role to staff.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = $staff->user;

            if (!$user) {
                if (empty($validated['password'])) {
                    return response()->json([
                        'message' => 'Password is required when creating staff access.',
                    ], 422);
                }

                $user = User::create([
                    'name' => $staff->full_name,
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role_id' => $role->id,
                    'staff_id' => $staff->id,
                ]);
            } else {
                $update = [
                    'name' => $staff->full_name,
                    'email' => $validated['email'],
                    'role_id' => $role->id,
                ];
                if (!empty($validated['password'])) {
                    $update['password'] = Hash::make($validated['password']);
                }
                $user->update($update);
            }

            $staff->load(['modules', 'user.role.permissions']);

            DB::commit();
            return response()->json([
                'staff' => $this->formatStaff($staff),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update staff access: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nic_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('staffs')->ignore($staff->id),
            ],
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'gender' => 'required|in:male,female,other',
            'blood_group' => 'nullable|string|max:10',
            'school_name' => 'nullable|string|max:255',
            'qualifications' => 'nullable|string',
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer|exists:modules,id',
            'secondary_phone' => 'required|string|max:20',
            'secondary_phone_has_whatsapp' => 'boolean',
            'medical_notes' => 'nullable|string',
            'image_path' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'status' => 'required|in:active,inactive,on_leave,terminated',
        ]);

        DB::beginTransaction();
        try {
            $imagePath = $staff->image_path;
            if ($request->hasFile('image')) {
                MediaDisk::deleteIfExists($staff->image_path);
                $imagePath = MediaDisk::storeUpload($request->file('image'), 'staff/'.$staff->id);
            } elseif ($request->exists('image_path')) {
                $imagePath = $validated['image_path'] ?? null;
            }

            $staff->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'nic_number' => $validated['nic_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'],
                'address' => $validated['address'],
                'gender' => $validated['gender'],
                'blood_group' => $validated['blood_group'] ?? null,
                'school_name' => $validated['school_name'] ?? null,
                'qualifications' => $validated['qualifications'] ?? null,
                'secondary_phone' => $validated['secondary_phone'],
                'secondary_phone_has_whatsapp' => $validated['secondary_phone_has_whatsapp'] ?? false,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'image_path' => $imagePath,
                'status' => $validated['status'],
            ]);

            $staff->modules()->sync($validated['module_ids'] ?? []);

            $staff->load('modules');

            DB::commit();

            return response()->json([
                'staff' => $this->formatStaff($staff->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update staff: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Staff $staff)
    {
        DB::transaction(function () use ($staff) {
            $staff->modules()->detach();
            $staff->status = 'inactive';
            $staff->save();
        });

        return response()->json(['message' => 'Staff removed from the directory successfully']);
    }

    private function generateBarcode(): string
    {
        // Generate a unique barcode for staff
        $timestamp = time();
        $hash = substr(md5(uniqid(rand(), true) . $timestamp), 0, 8);
        $barcode = 'STF' . strtoupper($hash);
        
        // Ensure uniqueness
        $attempts = 0;
        while (Staff::where('barcode', $barcode)->exists() && $attempts < 10) {
            $timestamp = time() + $attempts;
            $hash = substr(md5(uniqid(rand(), true) . $timestamp), 0, 8);
            $barcode = 'STF' . strtoupper($hash);
            $attempts++;
        }
        
        return $barcode;
    }

    private function formatStaff(Staff $staff): array
    {
        $staff->loadMissing(['user.role.permissions']);
        $account = null;
        if ($staff->user && $staff->user->role) {
            $account = [
                'userId' => $staff->user->id,
                'email' => $staff->user->email,
                'role' => [
                    'id' => $staff->user->role->id,
                    'name' => $staff->user->role->name,
                    'slug' => $staff->user->role->slug,
                    'permissions' => $staff->user->role->permissions->pluck('permission')->toArray(),
                ],
            ];
        }

        return [
            'id' => $staff->id,
            'firstName' => $staff->first_name,
            'lastName' => $staff->last_name,
            'fullName' => $staff->full_name,
            'nicNumber' => $staff->nic_number,
            'barcode' => $staff->barcode,
            'dateOfBirth' => $staff->date_of_birth->format('Y-m-d'),
            'address' => $staff->address,
            'gender' => $staff->gender,
            'bloodGroup' => $staff->blood_group,
            'schoolName' => $staff->school_name,
            'qualifications' => $staff->qualifications,
            'secondaryPhone' => $staff->secondary_phone,
            'secondaryPhoneHasWhatsapp' => $staff->secondary_phone_has_whatsapp,
            'medicalNotes' => $staff->medical_notes,
            'imagePath' => MediaDisk::publicUrl($staff->image_path),
            'status' => $staff->status,
            'account' => $account,
            'modules' => $staff->modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'category' => $module->category,
                    'amount' => (float) $module->amount,
                ];
            }),
            'createdAt' => $staff->created_at->toISOString(),
            'updatedAt' => $staff->updated_at->toISOString(),
        ];
    }
}
