<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Module;
use App\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function attendanceReport(Request $request)
    {
        $query = Attendance::with(['student', 'staff']);

        // Apply filters
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->has('month') && $request->month) {
            $query->whereMonth('date', Carbon::parse($request->month)->month)
                  ->whereYear('date', Carbon::parse($request->month)->year);
        }

        if ($request->has('year') && $request->year) {
            $query->whereYear('date', $request->year);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Summary over full filtered set (not current page)
        $summary = [
            'total_records' => (clone $query)->count(),
            'present' => (clone $query)->where('status', 'present')->count(),
            'late' => (clone $query)->where('status', 'late')->count(),
            'early_leave' => (clone $query)->where('status', 'early_leave')->count(),
            'absent' => (clone $query)->where('status', 'absent')->count(),
            'students' => (clone $query)->where('type', 'student')->count(),
            'staff' => (clone $query)->where('type', 'staff')->count(),
        ];

        $mapRow = function ($attendance) {
            return [
                'id' => $attendance->id,
                'type' => $attendance->type,
                'name' => $attendance->student ? $attendance->student->full_name : $attendance->staff->full_name,
                'admission_number' => $attendance->student ? $attendance->student->admission_number : null,
                'date' => $attendance->date->format('Y-m-d'),
                'time_in' => $attendance->time_in,
                'time_out' => $attendance->time_out,
                'status' => $attendance->status,
            ];
        };

        if ($request->filled('page')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $paginator = (clone $query)->with(['student', 'staff'])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'attendances' => $paginator->getCollection()->map($mapRow)->values(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ]);
        }

        $attendances = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'attendances' => $attendances->map($mapRow),
            'summary' => $summary,
        ]);
    }

    public function financialReport(Request $request)
    {
        $query = Payment::with(['student', 'module']);

        // Apply filters
        if ($request->has('date_from') && $request->date_from) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        if ($request->has('batch') && $request->batch) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('admission_batch', $request->batch);
            });
        }

        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        $summary = [
            'total_payments' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('amount'),
            'total_discount' => (clone $query)->sum('discount_amount'),
            'total_paid' => (clone $query)->sum('paid_amount'),
            'cash_payments' => (clone $query)->where('payment_method', 'cash')->sum('paid_amount'),
            'card_payments' => (clone $query)->where('payment_method', 'card')->sum('paid_amount'),
            'by_status' => [
                'paid' => (clone $query)->where('status', 'paid')->count(),
                'partial' => (clone $query)->where('status', 'partial')->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
            ],
        ];

        $mapPayment = function ($payment) {
            return [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'student_name' => $payment->student->full_name,
                'admission_number' => $payment->student->admission_number,
                'batch' => $payment->student->admission_batch,
                'module' => $payment->module ? $payment->module->name : 'All Modules',
                'amount' => $payment->amount,
                'discount_amount' => $payment->discount_amount,
                'paid_amount' => $payment->paid_amount,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'month' => $payment->month,
                'status' => $payment->status,
            ];
        };

        if ($request->filled('page')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $paginator = (clone $query)->with(['student', 'module'])
                ->orderBy('payment_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'payments' => $paginator->getCollection()->map($mapPayment)->values(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ]);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'payments' => $payments->map($mapPayment),
            'summary' => $summary,
        ]);
    }

    public function enrollmentReport(Request $request)
    {
        $query = Student::with('modules');

        // Apply filters
        if ($request->has('batch') && $request->batch) {
            $query->where('admission_batch', $request->batch);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->has('month') && $request->month) {
            $query->whereMonth('created_at', Carbon::parse($request->month)->month)
                  ->whereYear('created_at', Carbon::parse($request->month)->year);
        }

        if ($request->has('year') && $request->year) {
            $query->whereYear('created_at', $request->year);
        }

        $summary = [
            'total_students' => (clone $query)->count(),
            'by_batch' => (clone $query)
                ->select('admission_batch', DB::raw('count(*) as aggregate'))
                ->groupBy('admission_batch')
                ->pluck('aggregate', 'admission_batch'),
            'by_status' => (clone $query)
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'by_payment_type' => (clone $query)
                ->select('payment_type', DB::raw('count(*) as aggregate'))
                ->groupBy('payment_type')
                ->pluck('aggregate', 'payment_type'),
            'total_revenue' => (clone $query)->sum('paid_amount'),
            'total_module_fees' => (clone $query)->sum('module_total_amount'),
        ];

        $studentIdSubquery = (clone $query)->select('students.id');
        $moduleEnrollments = DB::table('module_student')
            ->join('modules', 'module_student.module_id', '=', 'modules.id')
            ->whereIn('module_student.student_id', $studentIdSubquery)
            ->select('modules.name as module_name', DB::raw('count(distinct module_student.student_id) as aggregate'))
            ->groupBy('modules.id', 'modules.name')
            ->orderBy('modules.name')
            ->get()
            ->map(fn ($row) => [
                'module_name' => $row->module_name,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $mapStudent = function ($student) {
            return [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'full_name' => $student->full_name,
                'batch' => $student->admission_batch,
                'status' => $student->status,
                'payment_type' => $student->payment_type,
                'admission_fee' => $student->admission_fee,
                'module_total_amount' => $student->module_total_amount,
                'paid_amount' => $student->paid_amount,
                'modules' => $student->modules->pluck('name')->toArray(),
                'enrolled_date' => $student->created_at->format('Y-m-d'),
            ];
        };

        if ($request->filled('page')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $paginator = (clone $query)->with('modules')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'students' => $paginator->getCollection()->map($mapStudent)->values(),
                'summary' => $summary,
                'module_enrollments' => $moduleEnrollments,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ]);
        }

        $students = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'students' => $students->map($mapStudent),
            'summary' => $summary,
            'module_enrollments' => $moduleEnrollments,
        ]);
    }

    public function performanceReport(Request $request)
    {
        $query = Questionnaire::with(['module', 'questions']);

        // Apply filters
        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->has('batch') && $request->batch) {
            $query->where('batch', $request->batch);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->has('month') && $request->month) {
            $query->whereMonth('created_at', Carbon::parse($request->month)->month)
                  ->whereYear('created_at', Carbon::parse($request->month)->year);
        }

        if ($request->has('year') && $request->year) {
            $query->whereYear('created_at', $request->year);
        }

        $totalCount = (clone $query)->count();
        $totalQuestionsSum = (clone $query)->sum('total_questions');

        $byModuleRows = (clone $query)
            ->leftJoin('modules', 'questionnaires.module_id', '=', 'modules.id')
            ->select(DB::raw("COALESCE(modules.name, 'General') as module_label"), DB::raw('count(*) as aggregate'))
            ->groupBy(DB::raw("COALESCE(modules.name, 'General')"))
            ->pluck('aggregate', 'module_label');

        $byBatchRows = (clone $query)
            ->select('batch', DB::raw('count(*) as aggregate'))
            ->groupBy('batch')
            ->pluck('aggregate', 'batch');

        $summary = [
            'total_questionnaires' => $totalCount,
            'by_module' => $byModuleRows,
            'by_batch' => $byBatchRows,
            'total_questions' => $totalQuestionsSum,
            'average_questions_per_paper' => $totalCount > 0
                ? round($totalQuestionsSum / $totalCount, 2)
                : 0,
        ];

        $mapQuestionnaire = function ($questionnaire) {
            return [
                'id' => $questionnaire->id,
                'title' => $questionnaire->title,
                'module' => $questionnaire->module ? $questionnaire->module->name : 'General',
                'batch' => $questionnaire->batch,
                'total_questions' => $questionnaire->total_questions,
                'question_counts' => $questionnaire->question_counts,
                'categories' => $questionnaire->selected_categories,
                'created_date' => $questionnaire->created_at->format('Y-m-d'),
            ];
        };

        if ($request->filled('page')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $paginator = (clone $query)->with(['module', 'questions'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'questionnaires' => $paginator->getCollection()->map($mapQuestionnaire)->values(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ]);
        }

        $questionnaires = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'questionnaires' => $questionnaires->map($mapQuestionnaire),
            'summary' => $summary,
        ]);
    }

    public function exportAttendanceReport(Request $request)
    {
        $data = $this->attendanceReport($request);
        $attendances = collect(json_decode($data->getContent(), true)['attendances']);
        $summary = json_decode($data->getContent(), true)['summary'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Report');

        // Header
        $sheet->setCellValue('A1', 'Attendance Report');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Summary');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Records');
        $sheet->setCellValue('B' . $row, $summary['total_records']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Present');
        $sheet->setCellValue('B' . $row, $summary['present']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Late');
        $sheet->setCellValue('B' . $row, $summary['late']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Early Leave');
        $sheet->setCellValue('B' . $row, $summary['early_leave']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Absent');
        $sheet->setCellValue('B' . $row, $summary['absent']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Students');
        $sheet->setCellValue('B' . $row, $summary['students']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Staff');
        $sheet->setCellValue('B' . $row, $summary['staff']);

        // Data headers
        $row += 2;
        $headers = ['Type', 'Name', 'Admission Number', 'Date', 'Time In', 'Time Out', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Data rows
        $row++;
        foreach ($attendances as $attendance) {
            $sheet->setCellValue('A' . $row, ucfirst($attendance['type']));
            $sheet->setCellValue('B' . $row, $attendance['name']);
            $sheet->setCellValue('C' . $row, $attendance['admission_number'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $attendance['date']);
            $sheet->setCellValue('E' . $row, $attendance['time_in'] ?? 'N/A');
            $sheet->setCellValue('F' . $row, $attendance['time_out'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, ucfirst(str_replace('_', ' ', $attendance['status'])));
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'attendance_report_' . date('Y-m-d') . '.xlsx');
    }

    public function exportFinancialReport(Request $request)
    {
        $data = $this->financialReport($request);
        $payments = collect(json_decode($data->getContent(), true)['payments']);
        $summary = json_decode($data->getContent(), true)['summary'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Financial Report');

        // Header
        $sheet->setCellValue('A1', 'Financial Report');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Summary');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $summaryData = [
            'Total Payments' => $summary['total_payments'],
            'Total Amount' => number_format($summary['total_amount'], 2),
            'Total Discount' => number_format($summary['total_discount'], 2),
            'Total Paid' => number_format($summary['total_paid'], 2),
            'Cash Payments' => number_format($summary['cash_payments'], 2),
            'Card Payments' => number_format($summary['card_payments'], 2),
        ];

        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $value);
            $row++;
        }

        // Data headers
        $row += 2;
        $headers = ['Receipt #', 'Student Name', 'Admission #', 'Batch', 'Module', 'Amount', 'Discount', 'Paid', 'Method', 'Date', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Data rows
        $row++;
        foreach ($payments as $payment) {
            $sheet->setCellValue('A' . $row, $payment['receipt_number'] ?? 'N/A');
            $sheet->setCellValue('B' . $row, $payment['student_name']);
            $sheet->setCellValue('C' . $row, $payment['admission_number']);
            $sheet->setCellValue('D' . $row, $payment['batch']);
            $sheet->setCellValue('E' . $row, $payment['module']);
            $sheet->setCellValue('F' . $row, number_format($payment['amount'], 2));
            $sheet->setCellValue('G' . $row, number_format($payment['discount_amount'], 2));
            $sheet->setCellValue('H' . $row, number_format($payment['paid_amount'], 2));
            $sheet->setCellValue('I' . $row, ucfirst($payment['payment_method']));
            $sheet->setCellValue('J' . $row, $payment['payment_date']);
            $sheet->setCellValue('K' . $row, ucfirst($payment['status']));
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'financial_report_' . date('Y-m-d') . '.xlsx');
    }

    public function exportEnrollmentReport(Request $request)
    {
        $data = $this->enrollmentReport($request);
        $students = collect(json_decode($data->getContent(), true)['students']);
        $summary = json_decode($data->getContent(), true)['summary'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Enrollment Report');

        // Header
        $sheet->setCellValue('A1', 'Student Enrollment Report');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Summary');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Students');
        $sheet->setCellValue('B' . $row, $summary['total_students']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Revenue');
        $sheet->setCellValue('B' . $row, number_format($summary['total_revenue'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Module Fees');
        $sheet->setCellValue('B' . $row, number_format($summary['total_module_fees'], 2));

        // Data headers
        $row += 2;
        $headers = ['Admission #', 'Name', 'Batch', 'Status', 'Payment Type', 'Admission Fee', 'Module Fees', 'Paid Amount', 'Modules', 'Enrolled Date'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Data rows
        $row++;
        foreach ($students as $student) {
            $sheet->setCellValue('A' . $row, $student['admission_number']);
            $sheet->setCellValue('B' . $row, $student['full_name']);
            $sheet->setCellValue('C' . $row, $student['batch']);
            $sheet->setCellValue('D' . $row, ucfirst($student['status']));
            $sheet->setCellValue('E' . $row, ucfirst(str_replace('_', ' ', $student['payment_type'])));
            $sheet->setCellValue('F' . $row, number_format($student['admission_fee'], 2));
            $sheet->setCellValue('G' . $row, number_format($student['module_total_amount'], 2));
            $sheet->setCellValue('H' . $row, number_format($student['paid_amount'], 2));
            $sheet->setCellValue('I' . $row, implode(', ', $student['modules']));
            $sheet->setCellValue('J' . $row, $student['enrolled_date']);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'enrollment_report_' . date('Y-m-d') . '.xlsx');
    }

    public function exportPerformanceReport(Request $request)
    {
        $data = $this->performanceReport($request);
        $questionnaires = collect(json_decode($data->getContent(), true)['questionnaires']);
        $summary = json_decode($data->getContent(), true)['summary'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Performance Report');

        // Header
        $sheet->setCellValue('A1', 'Course Performance Report');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Summary
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Summary');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Questionnaires');
        $sheet->setCellValue('B' . $row, $summary['total_questionnaires']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Questions');
        $sheet->setCellValue('B' . $row, $summary['total_questions']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Average Questions per Paper');
        $sheet->setCellValue('B' . $row, $summary['average_questions_per_paper']);

        // Data headers
        $row += 2;
        $headers = ['Title', 'Module', 'Batch', 'Total Questions', 'Question Types', 'Categories', 'Created Date'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Data rows
        $row++;
        foreach ($questionnaires as $questionnaire) {
            $sheet->setCellValue('A' . $row, $questionnaire['title']);
            $sheet->setCellValue('B' . $row, $questionnaire['module']);
            $sheet->setCellValue('C' . $row, $questionnaire['batch']);
            $sheet->setCellValue('D' . $row, $questionnaire['total_questions']);
            
            // Format question counts
            $questionTypes = [];
            foreach ($questionnaire['question_counts'] as $type => $count) {
                if ($count > 0) {
                    $questionTypes[] = ucfirst(str_replace('_', ' ', $type)) . ': ' . $count;
                }
            }
            $sheet->setCellValue('E' . $row, implode(', ', $questionTypes));
            $sheet->setCellValue('F' . $row, implode(', ', $questionnaire['categories']));
            $sheet->setCellValue('G' . $row, $questionnaire['created_date']);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'performance_report_' . date('Y-m-d') . '.xlsx');
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer->save($tempFile);

        return Response::download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
