<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\MediaDisk;
use App\Models\Student;
use App\Models\Module;
use App\Models\ModuleFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['student', 'module', 'createdBy']);

        // Filter by batch
        if ($request->has('batch') && $request->batch) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('admission_batch', $request->batch);
            });
        }

        // Filter by module_id
        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        // Filter by month (format: YYYY-MM)
        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        // Filter by year
        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // Search by student admission number or name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('admission_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $page = (int) $request->get('page', 1);
        $perPage = 20;

        $payments = $query->orderBy('created_at', 'desc')
            ->orderBy('payment_date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'payments' => $payments->map(function ($payment) {
                return $this->formatPayment($payment);
            }),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
                'has_more_pages' => $payments->hasMorePages(),
            ],
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load(['student.modules', 'module', 'createdBy']);
        return response()->json([
            'payment' => $this->formatPayment($payment, true),
        ]);
    }

    public function searchStudent(Request $request)
    {
        $request->validate([
            'admission_number' => 'nullable|string',
            'barcode' => 'nullable|string',
        ]);

        $query = Student::with(['modules', 'payments' => function ($q) {
            $q->orderBy('payment_date', 'desc')->limit(10);
        }]);

        if ($request->has('admission_number') && $request->admission_number) {
            $query->where('admission_number', $request->admission_number);
        } elseif ($request->has('barcode') && $request->barcode) {
            $query->where('barcode', $request->barcode);
        } else {
            return response()->json([
                'message' => 'Either admission_number or barcode is required',
            ], 422);
        }

        $student = $query->first();

        if (!$student) {
            return response()->json([
                'message' => 'Student not found',
            ], 404);
        }

        // Calculate monthly fee based on enrolled modules
        $monthlyFee = $this->calculateMonthlyFee($student);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'barcode' => $student->barcode,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'admission_batch' => $student->admission_batch,
                'personal_phone' => $student->personal_phone,
                'parent_phone' => $student->parent_phone,
                'image_path' => MediaDisk::publicUrl($student->image_path),
                'modules' => $student->modules->map(function ($module) {
                    return [
                        'id' => $module->id,
                        'name' => $module->name,
                        'category' => $module->category,
                    ];
                }),
                'monthly_fee' => $monthlyFee,
                'recent_payments' => $student->payments->map(function ($payment) {
                    return $this->formatPayment($payment);
                }),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'module_id' => 'nullable|integer|exists:modules,id',
            'amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card',
            'payment_date' => 'required|date',
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'status' => 'nullable|in:pending,paid,partial',
            'notes' => 'nullable|string',
        ]);

        // Validate discount doesn't exceed amount
        if (isset($validated['discount_amount']) && $validated['discount_amount'] > $validated['amount']) {
            return response()->json([
                'message' => 'Discount amount cannot exceed total amount',
            ], 422);
        }

        // Validate paid amount
        $discount = $validated['discount_amount'] ?? 0;
        $amountAfterDiscount = $validated['amount'] - $discount;
        
        if ($validated['paid_amount'] > $amountAfterDiscount) {
            return response()->json([
                'message' => 'Paid amount cannot exceed amount after discount',
            ], 422);
        }

        // Determine status
        if (!isset($validated['status'])) {
            if ($validated['paid_amount'] == $amountAfterDiscount) {
                $validated['status'] = 'paid';
            } elseif ($validated['paid_amount'] > 0) {
                $validated['status'] = 'partial';
            } else {
                $validated['status'] = 'pending';
            }
        }

        // Extract year from month
        $validated['year'] = (int) substr($validated['month'], 0, 4);

        DB::beginTransaction();
        try {
            // Generate receipt number
            $receiptNumber = $this->generateReceiptNumber();

            $payment = Payment::create([
                'student_id' => $validated['student_id'],
                'module_id' => $validated['module_id'] ?? null,
                'amount' => $validated['amount'],
                'discount_amount' => $discount,
                'paid_amount' => $validated['paid_amount'],
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'month' => $validated['month'],
                'year' => $validated['year'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'receipt_number' => $receiptNumber,
                'created_by' => Auth::id(),
            ]);

            // Update student's paid_amount
            $student = Student::findOrFail($validated['student_id']);
            $student->increment('paid_amount', $validated['paid_amount']);

            $payment->load(['student', 'module', 'createdBy']);

            DB::commit();

            return response()->json([
                'payment' => $this->formatPayment($payment),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card',
            'payment_date' => 'nullable|date',
            'status' => 'nullable|in:pending,paid,partial',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $oldPaidAmount = $payment->paid_amount;

            $payment->update($validated);

            // Update student's paid_amount if paid_amount changed
            if (isset($validated['paid_amount']) && $validated['paid_amount'] != $oldPaidAmount) {
                $difference = $validated['paid_amount'] - $oldPaidAmount;
                $payment->student->increment('paid_amount', $difference);
            }

            $payment->load(['student', 'module', 'createdBy']);

            DB::commit();

            return response()->json([
                'payment' => $this->formatPayment($payment),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Payment $payment)
    {
        DB::beginTransaction();
        try {
            // Decrement student's paid_amount
            $payment->student->decrement('paid_amount', $payment->paid_amount);
            
            $payment->delete();

            DB::commit();

            return response()->json(['message' => 'Payment deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getBatches()
    {
        $batches = Student::select('admission_batch')
            ->distinct()
            ->orderBy('admission_batch', 'desc')
            ->pluck('admission_batch');

        return response()->json(['batches' => $batches]);
    }

    public function getMonthlySummary(Request $request)
    {
        $query = Payment::query();

        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        $summary = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'total_discount' => $query->sum('discount_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'cash_payments' => (clone $query)->where('payment_method', 'cash')->sum('paid_amount'),
            'card_payments' => (clone $query)->where('payment_method', 'card')->sum('paid_amount'),
        ];

        return response()->json(['summary' => $summary]);
    }

    private function calculateMonthlyFee(Student $student): float
    {
        $totalFee = 0;
        $currentDate = Carbon::now();

        foreach ($student->modules as $module) {
            $moduleFee = ModuleFee::where('module_id', $module->id)
                ->where('effective_from', '<=', $currentDate)
                ->where(function ($q) use ($currentDate) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $currentDate);
                })
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($moduleFee) {
                $totalFee += $moduleFee->monthly_fee;
            } else {
                // Fallback to module amount if no fee structure exists
                $totalFee += $module->amount ?? 0;
            }
        }

        return $totalFee;
    }

    private function generateReceiptNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastPayment = Payment::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPayment ? (int) substr($lastPayment->receipt_number ?? '0', -4) + 1 : 1;

        return sprintf('RCP-%s%s-%04d', $year, $month, $sequence);
    }

    private function formatPayment(Payment $payment, $includeDetails = false): array
    {
        $data = [
            'id' => $payment->id,
            'student_id' => $payment->student_id,
            'student' => [
                'id' => $payment->student->id,
                'admission_number' => $payment->student->admission_number,
                'full_name' => $payment->student->full_name,
                'admission_batch' => $payment->student->admission_batch,
            ],
            'module_id' => $payment->module_id,
            'module' => $payment->module ? [
                'id' => $payment->module->id,
                'name' => $payment->module->name,
            ] : null,
            'amount' => (float) $payment->amount,
            'discount_amount' => (float) $payment->discount_amount,
            'paid_amount' => (float) $payment->paid_amount,
            'payment_method' => $payment->payment_method,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'month' => $payment->month,
            'year' => $payment->year,
            'status' => $payment->status,
            'notes' => $payment->notes,
            'receipt_number' => $payment->receipt_number,
            'created_by' => $payment->createdBy ? [
                'id' => $payment->createdBy->id,
                'name' => $payment->createdBy->name ?? 'System',
            ] : null,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ];

        if ($includeDetails) {
            $data['student']['modules'] = $payment->student->modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'category' => $module->category,
                ];
            });
        }

        return $data;
    }
}
