<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PhotoLibraryController;
use App\Http\Controllers\Api\StaffPayrollController;
use App\Http\Controllers\Api\PublicWebsiteController;
use App\Http\Controllers\Api\GalleryController;

Route::prefix('v1')->group(function () {
    // Public website (no auth)
    Route::get('/public/modules', [PublicWebsiteController::class, 'modules']);
    Route::get('/public/staff', [PublicWebsiteController::class, 'staff']);
    Route::get('/public/staff/{staff}', [PublicWebsiteController::class, 'staffShow']);
    Route::get('/public/gallery/categories', [PublicWebsiteController::class, 'galleryCategories']);
    Route::get('/public/gallery/categories/{galleryCategory}/images', [PublicWebsiteController::class, 'galleryCategoryImages']);

    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Staffs
        Route::get('/staffs', [StaffController::class, 'index']);
        Route::post('/staffs', [StaffController::class, 'store']);
        Route::get('/staffs/{staff}', [StaffController::class, 'show']);
        Route::put('/staffs/{staff}', [StaffController::class, 'update']);
        Route::delete('/staffs/{staff}', [StaffController::class, 'destroy']);

        // Modules
        Route::get('/modules', [ModuleController::class, 'index']);
        Route::post('/modules', [ModuleController::class, 'store']);
        Route::get('/modules/{module}', [ModuleController::class, 'show']);
        Route::put('/modules/{module}', [ModuleController::class, 'update']);
        Route::delete('/modules/{module}', [ModuleController::class, 'destroy']);

        // Students
        Route::get('/students', [StudentController::class, 'index']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/{student}', [StudentController::class, 'show']);
        Route::put('/students/{student}', [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
        Route::post('/students/{student}/deactivate', [StudentController::class, 'deactivate']);

        // Timetables
        Route::get('/timetables', [TimetableController::class, 'index']);
        Route::post('/timetables', [TimetableController::class, 'store']);
        Route::get('/timetables/{timetable}', [TimetableController::class, 'show']);
        Route::put('/timetables/{timetable}', [TimetableController::class, 'update']);
        Route::delete('/timetables/{timetable}', [TimetableController::class, 'destroy']);
        Route::get('/timetables/{timetable}/download', [TimetableController::class, 'download']);

        // Classrooms
        Route::get('/classrooms', [ClassroomController::class, 'index']);
        Route::post('/classrooms', [ClassroomController::class, 'store']);
        Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show']);
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update']);
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy']);

        // Questions
        Route::get('/questions', [QuestionController::class, 'index']);
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::get('/questions/categories', [QuestionController::class, 'getCategories']); // Must be before {question} route
        Route::get('/questions/{question}', [QuestionController::class, 'show']);
        Route::put('/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);

        // Questionnaires
        Route::get('/questionnaires', [QuestionnaireController::class, 'index']);
        Route::post('/questionnaires', [QuestionnaireController::class, 'store']);
        Route::get('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'show']);
        Route::delete('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'destroy']);

        // Payments
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/search-student', [PaymentController::class, 'searchStudent']);
        Route::get('/payments/batches', [PaymentController::class, 'getBatches']);
        Route::get('/payments/monthly-summary', [PaymentController::class, 'getMonthlySummary']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}', [PaymentController::class, 'update']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

        // Salary & payroll (staff)
        Route::get('/staff-payroll/staff/{staff}', [StaffPayrollController::class, 'staffSummary']);
        Route::get('/staff-payroll', [StaffPayrollController::class, 'index']);
        Route::post('/staff-payroll', [StaffPayrollController::class, 'store']);
        Route::get('/staff-payroll/{staff_payroll_payment}', [StaffPayrollController::class, 'show']);
        Route::put('/staff-payroll/{staff_payroll_payment}', [StaffPayrollController::class, 'update']);
        Route::delete('/staff-payroll/{staff_payroll_payment}', [StaffPayrollController::class, 'destroy']);

        // Attendance
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::post('/attendances/mark', [AttendanceController::class, 'markAttendance']);
        Route::get('/attendances/search-barcode', [AttendanceController::class, 'searchByBarcode']);
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroy']);

        // Drive (folders & files; API path unchanged)
        Route::get('/photo-library/folders', [PhotoLibraryController::class, 'indexFolders']);
        Route::post('/photo-library/folders', [PhotoLibraryController::class, 'storeFolder']);
        Route::put('/photo-library/folders/{folder}', [PhotoLibraryController::class, 'updateFolder']);
        Route::delete('/photo-library/folders/{folder}', [PhotoLibraryController::class, 'destroyFolder']);
        Route::get('/photo-library/folders/{folder}/images', [PhotoLibraryController::class, 'indexImages']);
        Route::post('/photo-library/folders/{folder}/images', [PhotoLibraryController::class, 'storeImage']);
        Route::put('/photo-library/images/{photoLibraryImage}', [PhotoLibraryController::class, 'updateImage']);
        Route::delete('/photo-library/images/{photoLibraryImage}', [PhotoLibraryController::class, 'destroyImage']);

        // Public website gallery (categories & images; same permission as Drive)
        Route::get('/gallery/categories', [GalleryController::class, 'indexCategories']);
        Route::post('/gallery/categories', [GalleryController::class, 'storeCategory']);
        Route::put('/gallery/categories/{galleryCategory}', [GalleryController::class, 'updateCategory']);
        Route::delete('/gallery/categories/{galleryCategory}', [GalleryController::class, 'destroyCategory']);
        Route::get('/gallery/categories/{galleryCategory}/images', [GalleryController::class, 'indexImages']);
        Route::post('/gallery/categories/{galleryCategory}/images/bulk', [GalleryController::class, 'storeBulkImages']);
        Route::delete('/gallery/images/{galleryImage}', [GalleryController::class, 'destroyImage']);

        // Reports
        Route::get('/reports/attendance', [ReportController::class, 'attendanceReport']);
        Route::get('/reports/financial', [ReportController::class, 'financialReport']);
        Route::get('/reports/enrollment', [ReportController::class, 'enrollmentReport']);
        Route::get('/reports/performance', [ReportController::class, 'performanceReport']);
        Route::get('/reports/attendance/export', [ReportController::class, 'exportAttendanceReport']);
        Route::get('/reports/financial/export', [ReportController::class, 'exportFinancialReport']);
        Route::get('/reports/enrollment/export', [ReportController::class, 'exportEnrollmentReport']);
        Route::get('/reports/performance/export', [ReportController::class, 'exportPerformanceReport']);

        // Roles - only super admin can access
        Route::middleware('superadmin')->group(function () {
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::get('/roles/{role}', [RoleController::class, 'show']);
            Route::put('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

            // Staff access (role + login) - only super admin
            Route::post('/staffs/{staff}/access', [StaffController::class, 'setAccess']);
        });
    });
});
