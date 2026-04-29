<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Module;
use App\Support\MediaDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = $page === 1 ? 10 : 15; // First page: 10 records, subsequent pages: 15 records

        $students = Student::with('modules')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'students' => $students->map(function ($student) {
                return $this->formatStudent($student);
            }),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
                'from' => $students->firstItem(),
                'to' => $students->lastItem(),
                'has_more_pages' => $students->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'nic_number' => 'nullable|string|max:20|unique:students,nic_number',
            'personal_phone' => 'required|string|max:20',
            'parent_phone' => 'required|string|max:20',
            'personal_phone_has_whatsapp' => 'boolean',
            'parent_phone_has_whatsapp' => 'boolean',
            'admission_batch' => 'required|string|max:50',
            'address' => 'required|string',
            'school_name' => 'nullable|string|max:255',
            'blood_group' => 'nullable|string|max:10',
            'medical_notes' => 'nullable|string',
            'image_path' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'module_ids' => 'required|array|min:1|max:3',
            'module_ids.*' => 'integer|exists:modules,id',
            'payment_type' => 'required|in:full,admission_only',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,graduated,suspended',
        ]);

        DB::beginTransaction();
        try {
            // Generate admission number
            $admissionNumber = $this->generateAdmissionNumber(
                $validated['admission_batch'],
                $validated['module_ids']
            );

            // Generate barcode (using admission number as base)
            $barcode = $this->generateBarcode($admissionNumber);

            // Calculate module total amount
            $modules = Module::whereIn('id', $validated['module_ids'])->get();
            $moduleTotalAmount = $modules->sum('amount');

            // Determine paid amount
            $paidAmount = $validated['paid_amount'] ?? 0;
            if ($validated['payment_type'] === 'full') {
                $paidAmount = 500 + $moduleTotalAmount; // 500 admission fee + module total
            } elseif ($validated['payment_type'] === 'admission_only') {
                $paidAmount = 500; // Only admission fee
            }

            $student = Student::create([
                'admission_number' => $admissionNumber,
                'barcode' => $barcode,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'nic_number' => $validated['nic_number'] ?? null,
                'personal_phone' => $validated['personal_phone'],
                'parent_phone' => $validated['parent_phone'],
                'personal_phone_has_whatsapp' => $validated['personal_phone_has_whatsapp'] ?? false,
                'parent_phone_has_whatsapp' => $validated['parent_phone_has_whatsapp'] ?? false,
                'admission_batch' => $validated['admission_batch'],
                'address' => $validated['address'],
                'school_name' => $validated['school_name'] ?? null,
                'blood_group' => $validated['blood_group'] ?? null,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'image_path' => $validated['image_path'] ?? null,
                'admission_fee' => 500.00,
                'module_total_amount' => $moduleTotalAmount,
                'paid_amount' => $paidAmount,
                'payment_type' => $validated['payment_type'],
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($request->hasFile('image')) {
                $path = MediaDisk::storeUpload($request->file('image'), 'students/'.$student->id);
                $student->image_path = $path;
                $student->save();
            }

            // Attach modules
            $student->modules()->sync($validated['module_ids']);

            $student->load('modules');

            DB::commit();

            return response()->json([
                'student' => $this->formatStudent($student),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create student: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Student $student)
    {
        $student->load('modules');
        return response()->json([
            'student' => $this->formatStudent($student),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'nic_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('students')->ignore($student->id),
            ],
            'personal_phone' => 'required|string|max:20',
            'parent_phone' => 'required|string|max:20',
            'personal_phone_has_whatsapp' => 'boolean',
            'parent_phone_has_whatsapp' => 'boolean',
            'admission_batch' => 'required|string|max:50',
            'address' => 'required|string',
            'school_name' => 'nullable|string|max:255',
            'blood_group' => 'nullable|string|max:10',
            'medical_notes' => 'nullable|string',
            'image_path' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'module_ids' => 'required|array|min:1|max:3',
            'module_ids.*' => 'integer|exists:modules,id',
            'payment_type' => 'required|in:full,admission_only',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,graduated,suspended',
        ]);

        DB::beginTransaction();
        try {
            $shouldRegenerateAdmissionNumber = false;

            if (($validated['admission_batch'] ?? null) !== $student->admission_batch) {
                $shouldRegenerateAdmissionNumber = true;
            }

            $currentModuleIds = $student->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all();
            sort($currentModuleIds);
            $incomingModuleIds = array_map('intval', $validated['module_ids'] ?? []);
            sort($incomingModuleIds);

            if ($incomingModuleIds !== $currentModuleIds) {
                $shouldRegenerateAdmissionNumber = true;
            }

            // Recalculate module total amount if modules changed
            if (isset($validated['module_ids'])) {
                $modules = Module::whereIn('id', $validated['module_ids'])->get();
                $moduleTotalAmount = $modules->sum('amount');
                $validated['module_total_amount'] = $moduleTotalAmount;

                // Update paid amount based on payment type
                if ($validated['payment_type'] === 'full') {
                    $validated['paid_amount'] = 500 + $moduleTotalAmount;
                } elseif ($validated['payment_type'] === 'admission_only') {
                    $validated['paid_amount'] = 500;
                }
            }

            if ($shouldRegenerateAdmissionNumber) {
                $validated['admission_number'] = $this->generateAdmissionNumber(
                    $validated['admission_batch'],
                    $validated['module_ids']
                );
            }

            $imagePath = $student->image_path;
            if ($request->hasFile('image')) {
                MediaDisk::deleteIfExists($student->image_path);
                $imagePath = MediaDisk::storeUpload($request->file('image'), 'students/'.$student->id);
            } elseif ($request->exists('image_path')) {
                $imagePath = $validated['image_path'] ?? null;
            }
            $validated['image_path'] = $imagePath;

            $student->update($validated);

            // Update modules if provided
            if (isset($validated['module_ids'])) {
                $student->modules()->sync($validated['module_ids']);
            }

            $student->load('modules');

            DB::commit();

            return response()->json([
                'student' => $this->formatStudent($student),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update student: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Student $student)
    {
        // Soft delete - make inactive instead of hard delete
        $student->status = 'inactive';
        $student->save();
        // $student->delete(); // Uncomment if you want to use soft delete

        return response()->json(['message' => 'Student marked as inactive successfully']);
    }

    public function deactivate(Student $student)
    {
        $student->status = 'inactive';
        $student->save();

        return response()->json(['message' => 'Student deactivated successfully']);
    }

    private function generateAdmissionNumber(string $batch, array $moduleIds): string
    {
        // Get last 2 digits of batch (e.g., "2026" -> "26")
        $batchDigits = substr($batch, -2);

        // Get first letters of selected modules (e.g., "Engineering Science Innovation" -> "ESI")
        $modules = Module::whereIn('id', $moduleIds)->orderBy('name')->get();
        $moduleLetters = $modules->map(function ($module) {
            return strtoupper(substr($module->name, 0, 1));
        })->implode('');

        // Sequential numbering (no random): {batchDigits}|{moduleLetters}|{n}
        // Example: 25|S|1, 25|S|2 ...
        $prefix = "{$batchDigits}|{$moduleLetters}|";

        $last = Student::query()
            ->where('admission_number', 'like', $prefix.'%')
            ->selectRaw("CAST(SUBSTRING_INDEX(admission_number, '|', -1) AS UNSIGNED) AS seq")
            ->orderByDesc('seq')
            ->value('seq');

        $next = ((int) ($last ?? 0)) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function generateBarcode(string $admissionNumber): string
    {
        // Generate a unique barcode based on admission number + timestamp
        // In production, you might use a barcode library to generate actual barcode image
        $timestamp = time();
        $hash = substr(md5($admissionNumber . $timestamp), 0, 8);
        return strtoupper($hash);
    }

    private function formatStudent(Student $student): array
    {
        return [
            'id' => $student->id,
            'admissionNumber' => $student->admission_number,
            'barcode' => $student->barcode,
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'fullName' => $student->full_name,
            'dateOfBirth' => $student->date_of_birth->format('Y-m-d'),
            'gender' => $student->gender,
            'nicNumber' => $student->nic_number,
            'personalPhone' => $student->personal_phone,
            'parentPhone' => $student->parent_phone,
            'personalPhoneHasWhatsapp' => $student->personal_phone_has_whatsapp,
            'parentPhoneHasWhatsapp' => $student->parent_phone_has_whatsapp,
            'admissionBatch' => $student->admission_batch,
            'address' => $student->address,
            'schoolName' => $student->school_name,
            'bloodGroup' => $student->blood_group,
            'medicalNotes' => $student->medical_notes,
            'imagePath' => MediaDisk::publicUrl($student->image_path),
            'admissionFee' => (float) $student->admission_fee,
            'moduleTotalAmount' => (float) $student->module_total_amount,
            'paidAmount' => (float) $student->paid_amount,
            'paymentType' => $student->payment_type,
            'status' => $student->status,
            'modules' => $student->modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'category' => $module->category,
                    'amount' => (float) $module->amount,
                ];
            }),
            'createdAt' => $student->created_at->toISOString(),
            'updatedAt' => $student->updated_at->toISOString(),
        ];
    }
}