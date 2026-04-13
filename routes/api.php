<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\CourseOfferingController as AdminCourseOfferingController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Lecturer\GradeController as LecturerGradeController;
use App\Http\Controllers\Api\Admin\GradeController as AdminGradeController;
use App\Http\Controllers\Api\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Api\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Lecturer\LecturerController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/change-password', [PasswordController::class, 'change']);
});

// Student Routes
Route::prefix('student')
    ->middleware(['auth:sanctum', 'role:student'])
    ->group(function () {
        Route::get('/dashboard',                        [StudentController::class, 'dashboard']);
        Route::get('/courses',                          [StudentController::class, 'courses']);
        Route::get('/timetable',                        [StudentController::class, 'timetable']);
        Route::get('/grades',                           [StudentController::class, 'grades']);
        Route::get('/gpa',                              [StudentController::class, 'gpaRecords']);
        Route::get('/notifications',                    [StudentController::class, 'notifications']);
        Route::patch('/notifications/read-all',         [StudentController::class, 'markAllNotificationsRead']);
        Route::patch('/notifications/{id}/read',        [StudentController::class, 'markNotificationRead']);
    });

// Lecturer routes
Route::prefix('lecturer')
    ->middleware(['auth:sanctum', 'role:lecturer'])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard',    [LecturerController::class, 'dashboard']);

        // Courses
        Route::get('/courses',      [LecturerController::class, 'courses']);

        // Students per course offering
        Route::get('/courses/{offering}/students', [LecturerController::class, 'students']);

        // Timetable
        Route::get('/timetable',    [LecturerController::class, 'timetable']);

        // Grades
        Route::get('/grades',               [LecturerGradeController::class, 'index']);
        Route::post('/grades',              [LecturerGradeController::class, 'submit']);
        Route::patch('/grades/{grade}',     [LecturerGradeController::class, 'update']);

        // Notifications
        Route::get('/notifications',               [LecturerController::class, 'notifications']);
        Route::patch('/notifications/read-all',    [LecturerController::class, 'markAllNotificationsRead']);
        Route::patch('/notifications/{id}/read',   [LecturerController::class, 'markNotificationRead']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {

        // User management
        Route::get('/users',                          [AdminUserController::class, 'index']);
        Route::post('/users',                         [AdminUserController::class, 'store']);
        Route::get('/users/{user}',                   [AdminUserController::class, 'show']);
        Route::post('/users/bulk-import',             [AdminUserController::class, 'bulkImport']);
        Route::get('/users/csv-template/{role}',      [AdminUserController::class, 'downloadCsvTemplate']);

        Route::middleware(['auth:sanctum', 'not.admin'])->group(function () {
            Route::get('/profile',              [ProfileController::class, 'show']);
            Route::patch('/profile',            [ProfileController::class, 'update']);
            Route::post('/profile/photo',       [ProfileController::class, 'updatePhoto']);
            Route::delete('/profile/photo',     [ProfileController::class, 'removePhoto']);
        });

        // Courses
        Route::get('/courses',                      [AdminCourseController::class, 'index']);
        Route::post('/courses',                     [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}',             [AdminCourseController::class, 'show']);
        Route::patch('/courses/{course}',           [AdminCourseController::class, 'update']);
        Route::patch('/courses/{course}/deactivate',[AdminCourseController::class, 'deactivate']);
        Route::patch('/courses/{course}/activate',  [AdminCourseController::class, 'activate']);

        // Course Offerings
        Route::get('/offerings',            [AdminCourseOfferingController::class, 'index']);
        Route::post('/offerings',           [AdminCourseOfferingController::class, 'store']);
        Route::get('/offerings/{offering}', [AdminCourseOfferingController::class, 'show']);

        // Enrollments
        Route::post('/enrollments',                    [AdminEnrollmentController::class, 'store']);
        Route::patch('/enrollments/{enrollment}/drop', [AdminEnrollmentController::class, 'drop']);

        // Grades
        Route::get('/grades',                       [AdminGradeController::class, 'index']);
        Route::get('/grades/pending',               [AdminGradeController::class, 'pending']);
        Route::patch('/grades/{grade}/review',      [AdminGradeController::class, 'review']);

        Route::get('/venues',           [AdminVenueController::class, 'index']);
        Route::post('/venues',          [AdminVenueController::class, 'store']);
        Route::get('/venues/{venue}',   [AdminVenueController::class, 'show']);
        Route::patch('/venues/{venue}', [AdminVenueController::class, 'update']);

        Route::get('/timetable',            [AdminTimetableController::class, 'index']);
        Route::post('/timetable',           [AdminTimetableController::class, 'store']);
        Route::get('/timetable/{slot}',     [AdminTimetableController::class, 'show']);
        Route::patch('/timetable/{slot}',   [AdminTimetableController::class, 'update']);
        Route::delete('/timetable/{slot}',  [AdminTimetableController::class, 'destroy']);
    });

// Rate limiter — 5 login attempts per minute per IP
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

Route::prefix('options')->group(function () {
    Route::get('/departments', [OptionsController::class, 'departments']);
    Route::get('/study-types', [OptionsController::class, 'studyTypes']);
    Route::get('/prefixes',    [OptionsController::class, 'prefixes']);
});

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});