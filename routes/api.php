<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\CourseOfferingController as AdminCourseOfferingController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {

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
    });

// Rate limiter — 5 login attempts per minute per IP
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

Route::prefix('options')->group(function () {
    Route::get('/departments', [OptionsController::class, 'departments']);
    Route::get('/study-types', [OptionsController::class, 'studyTypes']);
});

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});