<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['student', 'staff']);

        // Filter by type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Filter by student_id
        if ($request->has('student_id') && $request->student_id) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by staff_id
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }

        // Filter by date
        if ($request->has('date') && $request->date) {
            $query->where('date', $request->date);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search by name or barcode
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('staff', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $page = (int) $request->get('page', 1);
        $perPage = 20;

        $attendances = $query->orderBy('created_at', 'desc')
            ->orderBy('date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'attendances' => $attendances->map(function ($attendance) {
                return $this->formatAttendance($attendance);
            }),
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
                'last_page' => $attendances->lastPage(),
                'from' => $attendances->firstItem(),
                'to' => $attendances->lastItem(),
                'has_more_pages' => $attendances->hasMorePages(),
            ],
        ]);
    }

    public function markAttendance(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['student', 'staff'])],
            'barcode' => 'required|string',
            'action' => ['required', Rule::in(['in', 'out'])],
            'date' => 'nullable|date',
            'time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string',
        ]);

        $scanCode = $this->parseScanCode($validated['barcode']);
        if ($scanCode === '') {
            return response()->json([
                'message' => 'Invalid or empty QR / scan code',
            ], 422);
        }

        $date = $validated['date'] ?? Carbon::now()->format('Y-m-d');
        $time = $validated['time'] ?? Carbon::now()->format('H:i');

        DB::beginTransaction();
        try {
            if ($validated['type'] === 'student') {
                $student = Student::where('barcode', $scanCode)->first();
                
                if (!$student) {
                    return response()->json([
                        'message' => 'Student not found for this QR code',
                    ], 404);
                }

                // Check if attendance already exists for this student and date
                $attendance = Attendance::where('student_id', $student->id)
                    ->where('date', $date)
                    ->first();

                if (!$attendance) {
                    $attendance = new Attendance([
                        'type' => 'student',
                        'student_id' => $student->id,
                        'staff_id' => null,
                        'date' => $date,
                        'barcode' => $scanCode,
                    ]);
                }

                if ($validated['action'] === 'in') {
                    if ($attendance->time_in) {
                        return response()->json([
                            'message' => 'Attendance already marked for today',
                        ], 422);
                    }
                    $attendance->time_in = $time;
                    $attendance->status = $this->determineStatus($time, 'in');
                } else {
                    if (!$attendance->time_in) {
                        return response()->json([
                            'message' => 'Please mark time in first',
                        ], 422);
                    }
                    if ($attendance->time_out) {
                        return response()->json([
                            'message' => 'Time out already marked for today',
                        ], 422);
                    }
                    $attendance->time_out = $time;
                    if ($attendance->status === 'present') {
                        $attendance->status = $this->determineStatus($time, 'out', $attendance->time_in);
                    }
                }

                if (isset($validated['notes'])) {
                    $attendance->notes = $validated['notes'];
                }

                $attendance->save();
                $attendance->load('student');

            } else {
                $staff = Staff::where('barcode', $scanCode)->first();
                
                if (!$staff) {
                    return response()->json([
                        'message' => 'Staff not found for this QR code',
                    ], 404);
                }

                // Check if attendance already exists for this staff and date
                $attendance = Attendance::where('staff_id', $staff->id)
                    ->where('date', $date)
                    ->first();

                if (!$attendance) {
                    $attendance = new Attendance([
                        'type' => 'staff',
                        'student_id' => null,
                        'staff_id' => $staff->id,
                        'date' => $date,
                        'barcode' => $scanCode,
                    ]);
                }

                if ($validated['action'] === 'in') {
                    if ($attendance->time_in) {
                        return response()->json([
                            'message' => 'Attendance already marked for today',
                        ], 422);
                    }
                    $attendance->time_in = $time;
                    $attendance->status = $this->determineStatus($time, 'in');
                } else {
                    if (!$attendance->time_in) {
                        return response()->json([
                            'message' => 'Please mark time in first',
                        ], 422);
                    }
                    if ($attendance->time_out) {
                        return response()->json([
                            'message' => 'Time out already marked for today',
                        ], 422);
                    }
                    $attendance->time_out = $time;
                    if ($attendance->status === 'present') {
                        $attendance->status = $this->determineStatus($time, 'out', $attendance->time_in);
                    }
                }

                if (isset($validated['notes'])) {
                    $attendance->notes = $validated['notes'];
                }

                $attendance->save();
                $attendance->load('staff');
            }

            DB::commit();

            return response()->json([
                'attendance' => $this->formatAttendance($attendance),
                'message' => 'Attendance marked successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to mark attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'status' => ['nullable', Rule::in(['present', 'absent', 'late', 'early_leave'])],
            'notes' => 'nullable|string',
        ]);

        $attendance->update($validated);

        $attendance->load(['student', 'staff']);

        return response()->json([
            'attendance' => $this->formatAttendance($attendance),
        ]);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(['message' => 'Attendance deleted successfully']);
    }

    public function searchByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'type' => ['required', Rule::in(['student', 'staff'])],
        ]);

        $scanCode = $this->parseScanCode($request->barcode);
        if ($scanCode === '') {
            return response()->json([
                'message' => 'Invalid or empty QR / scan code',
            ], 422);
        }

        if ($request->type === 'student') {
            $student = Student::where('barcode', $scanCode)->first();

            if (! $student) {
                return response()->json([
                    'message' => 'Student not found',
                ], 404);
            }

            return response()->json($this->buildStudentScanResponse($student));
        }

        $staff = Staff::where('barcode', $scanCode)->first();

        if (! $staff) {
            return response()->json([
                'message' => 'Staff not found',
            ], 404);
        }

        return response()->json($this->buildStaffScanResponse($staff));
    }

    /**
     * Accepts raw QR text: plain barcode, JSON with barcode/b, or techna:student:CODE / techna:staff:CODE.
     */
    private function parseScanCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (! empty($decoded['barcode']) && is_string($decoded['barcode'])) {
                return trim($decoded['barcode']);
            }
            if (! empty($decoded['b']) && is_string($decoded['b'])) {
                return trim($decoded['b']);
            }
        }

        if (preg_match('/^techna:student:(.+)$/i', $raw, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/^techna:staff:(.+)$/i', $raw, $m)) {
            return trim($m[1]);
        }

        return $raw;
    }

    private function buildStudentScanResponse(Student $student): array
    {
        $student->load('modules');

        $todayAttendance = Attendance::where('student_id', $student->id)
            ->where('date', Carbon::now()->format('Y-m-d'))
            ->first();

        $since = Carbon::now()->subDays(90)->format('Y-m-d');

        $history = Attendance::with(['student', 'staff'])
            ->where('student_id', $student->id)
            ->where('date', '>=', $since)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(120)
            ->get();

        $statsBase = Attendance::where('student_id', $student->id)
            ->where('date', '>=', $since);

        $byStatus = (clone $statsBase)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n)
            ->toArray();

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->with('module')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return [
            'student' => $this->formatScanStudentProfile($student),
            'today_attendance' => $todayAttendance ? $this->formatAttendance($todayAttendance) : null,
            'attendance_history' => $history->map(fn (Attendance $a) => $this->formatAttendance($a))->values()->all(),
            'attendance_stats' => [
                'period_days' => 90,
                'since' => $since,
                'total_records' => (clone $statsBase)->count(),
                'by_status' => $byStatus,
            ],
            'payment_records' => $payments->map(function (Payment $p) {
                return [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'paid_amount' => (float) $p->paid_amount,
                    'discount_amount' => (float) $p->discount_amount,
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'month' => $p->month,
                    'year' => $p->year,
                    'status' => $p->status,
                    'payment_method' => $p->payment_method,
                    'module_name' => $p->module?->name,
                    'receipt_number' => $p->receipt_number,
                    'notes' => $p->notes,
                ];
            })->values()->all(),
        ];
    }

    private function buildStaffScanResponse(Staff $staff): array
    {
        $staff->load('modules');

        $todayAttendance = Attendance::where('staff_id', $staff->id)
            ->where('date', Carbon::now()->format('Y-m-d'))
            ->first();

        $since = Carbon::now()->subDays(90)->format('Y-m-d');

        $history = Attendance::with(['student', 'staff'])
            ->where('staff_id', $staff->id)
            ->where('date', '>=', $since)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(120)
            ->get();

        $statsBase = Attendance::where('staff_id', $staff->id)
            ->where('date', '>=', $since);

        $byStatus = (clone $statsBase)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n)
            ->toArray();

        return [
            'staff' => $this->formatScanStaffProfile($staff),
            'today_attendance' => $todayAttendance ? $this->formatAttendance($todayAttendance) : null,
            'attendance_history' => $history->map(fn (Attendance $a) => $this->formatAttendance($a))->values()->all(),
            'attendance_stats' => [
                'period_days' => 90,
                'since' => $since,
                'total_records' => (clone $statsBase)->count(),
                'by_status' => $byStatus,
            ],
        ];
    }

    private function formatScanStudentProfile(Student $student): array
    {
        return [
            'id' => $student->id,
            'admission_number' => $student->admission_number,
            'barcode' => $student->barcode,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'full_name' => $student->full_name,
            'image_path' => $student->image_path,
            'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
            'gender' => $student->gender,
            'nic_number' => $student->nic_number,
            'personal_phone' => $student->personal_phone,
            'parent_phone' => $student->parent_phone,
            'personal_phone_has_whatsapp' => (bool) $student->personal_phone_has_whatsapp,
            'parent_phone_has_whatsapp' => (bool) $student->parent_phone_has_whatsapp,
            'admission_batch' => $student->admission_batch,
            'address' => $student->address,
            'school_name' => $student->school_name,
            'blood_group' => $student->blood_group,
            'medical_notes' => $student->medical_notes,
            'admission_fee' => (float) $student->admission_fee,
            'module_total_amount' => (float) $student->module_total_amount,
            'paid_amount' => (float) $student->paid_amount,
            'payment_type' => $student->payment_type,
            'status' => $student->status,
            'modules' => $student->modules->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'category' => $m->category,
                'amount' => (float) $m->amount,
            ])->values()->all(),
        ];
    }

    private function formatScanStaffProfile(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'barcode' => $staff->barcode,
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'full_name' => $staff->full_name,
            'image_path' => $staff->image_path,
            'nic_number' => $staff->nic_number,
            'date_of_birth' => $staff->date_of_birth?->format('Y-m-d'),
            'gender' => $staff->gender,
            'address' => $staff->address,
            'blood_group' => $staff->blood_group,
            'school_name' => $staff->school_name,
            'qualifications' => $staff->qualifications,
            'secondary_phone' => $staff->secondary_phone,
            'secondary_phone_has_whatsapp' => (bool) $staff->secondary_phone_has_whatsapp,
            'medical_notes' => $staff->medical_notes,
            'status' => $staff->status,
            'modules' => $staff->modules->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'category' => $m->category,
                'amount' => (float) $m->amount,
            ])->values()->all(),
        ];
    }

    private function determineStatus($time, $action, $timeIn = null)
    {
        $timeObj = Carbon::createFromFormat('H:i', $time);
        $expectedTimeIn = Carbon::createFromTime(8, 0); // 8:00 AM
        $expectedTimeOut = Carbon::createFromTime(17, 0); // 5:00 PM

        if ($action === 'in') {
            if ($timeObj->gt($expectedTimeIn->copy()->addMinutes(15))) {
                return 'late';
            }
            return 'present';
        } else {
            if ($timeIn) {
                $timeInObj = Carbon::createFromFormat('H:i', $timeIn);
                $hoursWorked = $timeObj->diffInHours($timeInObj);
                
                if ($hoursWorked < 6) {
                    return 'early_leave';
                }
            }
            
            if ($timeObj->lt($expectedTimeOut->copy()->subMinutes(30))) {
                return 'early_leave';
            }
            
            return 'present';
        }
    }

    private function formatAttendance(Attendance $attendance): array
    {
        $data = [
            'id' => $attendance->id,
            'type' => $attendance->type,
            'date' => $attendance->date->format('Y-m-d'),
            'time_in' => $attendance->time_in ? Carbon::parse($attendance->time_in)->format('H:i') : null,
            'time_out' => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('H:i') : null,
            'status' => $attendance->status,
            'notes' => $attendance->notes,
            'barcode' => $attendance->barcode,
            'created_at' => $attendance->created_at,
            'updated_at' => $attendance->updated_at,
        ];

        if ($attendance->type === 'student' && $attendance->student) {
            $data['student'] = [
                'id' => $attendance->student->id,
                'admission_number' => $attendance->student->admission_number,
                'full_name' => $attendance->student->full_name,
                'barcode' => $attendance->student->barcode,
            ];
        }

        if ($attendance->type === 'staff' && $attendance->staff) {
            $data['staff'] = [
                'id' => $attendance->staff->id,
                'full_name' => $attendance->staff->full_name,
                'barcode' => $attendance->staff->barcode,
            ];
        }

        return $data;
    }
}
