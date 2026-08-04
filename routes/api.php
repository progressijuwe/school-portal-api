<?php

use App\Http\Controllers\Api\Admin\AcademicSessionController as AdminAcademicSessionController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\CourseOfferingController as AdminCourseOfferingController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\Admin\GradeController as AdminGradeController;
use App\Http\Controllers\Api\Admin\RegistrationReviewController;
use App\Http\Controllers\Api\Admin\ResultReviewController;
use App\Http\Controllers\Api\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Lecturer\GradeController as LecturerGradeController;
use App\Http\Controllers\Api\Lecturer\LecturerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Student\EnrollmentController as StudentEnrollmentController;
use App\Http\Controllers\Api\Student\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The `login` limiter is defined in AppServiceProvider and keyed by email +
| IP. The previous inline `throttle:5,1` was keyed on IP alone, and shadowed a
| named limiter that was declared here but never referenced by any route.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Records a lockout for an administrator to action — there is no mail
    // service, so no reset link is issued. Throttled on the same limiter as
    // login: the endpoint is unauthenticated and writes to a user row.
    Route::post('/forgot-password', [PasswordController::class, 'forgot'])
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [PasswordController::class, 'change']);
    });
});

/*
|--------------------------------------------------------------------------
| Public reference data
|--------------------------------------------------------------------------
*/

Route::prefix('options')->group(function () {
    Route::get('/departments', [OptionsController::class, 'departments']);
    Route::get('/study-types', [OptionsController::class, 'studyTypes']);
    Route::get('/prefixes', [OptionsController::class, 'prefixes']);
    Route::get('/academic-sessions', [OptionsController::class, 'academicSessions']);
    Route::get('/academic-rules', [OptionsController::class, 'academicRules']);
});

/*
|--------------------------------------------------------------------------
| Profile — every authenticated role
|--------------------------------------------------------------------------
*/

Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::patch('/', [ProfileController::class, 'update']);
    Route::post('/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:heavy');
    Route::delete('/photo', [ProfileController::class, 'removePhoto']);
});

/*
|--------------------------------------------------------------------------
| Student
|--------------------------------------------------------------------------
*/

Route::prefix('student')
    ->middleware(['auth:sanctum', 'role:student'])
    ->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard']);
        Route::get('/courses', [StudentController::class, 'courses']);
        Route::get('/timetable', [StudentController::class, 'timetable']);
        Route::get('/grades', [StudentController::class, 'grades']);
        Route::get('/gpa', [StudentController::class, 'gpaRecords']);
        // The whole academic record in one response — see the note on the
        // controller method for why it is not assembled from /grades.
        Route::get('/transcript', [StudentController::class, 'transcript']);

        Route::get('/available-offerings', [StudentEnrollmentController::class, 'availableOfferings']);
        Route::post('/enrollments', [StudentEnrollmentController::class, 'store']);
        Route::get('/enrollments', [StudentEnrollmentController::class, 'myEnrollments']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

/*
|--------------------------------------------------------------------------
| Lecturer
|--------------------------------------------------------------------------
*/

Route::prefix('lecturer')
    ->middleware(['auth:sanctum', 'role:lecturer'])
    ->group(function () {
        Route::get('/dashboard', [LecturerController::class, 'dashboard']);
        Route::get('/courses', [LecturerController::class, 'courses']);
        Route::get('/courses/{offering}/students', [LecturerController::class, 'students']);
        Route::get('/timetable', [LecturerController::class, 'timetable']);

        // Ownership of the target enrollment is enforced by EnrollmentPolicy
        // via the ValidatesGradeOwnership trait on each FormRequest.
        Route::get('/grades', [LecturerGradeController::class, 'index']);
        Route::post('/grades', [LecturerGradeController::class, 'submit']);
        Route::post('/grades/batch', [LecturerGradeController::class, 'batchSubmit']);
        Route::post('/grades/draft', [LecturerGradeController::class, 'saveDraft']);
        Route::patch('/grades/{grade}', [LecturerGradeController::class, 'update']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/activity', [AdminDashboardController::class, 'activity']);

        // Admins receive a notification every time a lecturer submits a mark
        // sheet. Until now there was no route on which to read them.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Users — static segments are declared before /{user} so a literal path
        // can never be swallowed by the wildcard.
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::post('/users/bulk-import', [AdminUserController::class, 'bulkImport'])->middleware('throttle:heavy');
        Route::get('/users/export', [AdminUserController::class, 'export'])->middleware('throttle:heavy');
        Route::get('/users/csv-template/{role}', [AdminUserController::class, 'downloadCsvTemplate']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
        Route::get('/users/{user}/summary', [AdminUserController::class, 'summary']);
        Route::get('/users/{user}/grades', [AdminUserController::class, 'studentGrades']);
        Route::get('/users/{user}/courses', [AdminUserController::class, 'lecturerCourses']);

        // Courses
        Route::get('/courses', [AdminCourseController::class, 'index']);
        // Declared before /{course} so the literal path is not swallowed.
        Route::get('/courses/export', [AdminCourseController::class, 'export'])->middleware('throttle:heavy');
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}', [AdminCourseController::class, 'show']);
        Route::patch('/courses/{course}', [AdminCourseController::class, 'update']);
        Route::patch('/courses/{course}/deactivate', [AdminCourseController::class, 'deactivate']);
        Route::patch('/courses/{course}/activate', [AdminCourseController::class, 'activate']);

        // Course offerings — no destroy route: enrollments reference an
        // offering with restrictOnDelete, so closing one means PATCHing
        // is_active to false rather than removing the row.
        Route::get('/offerings', [AdminCourseOfferingController::class, 'index']);
        Route::post('/offerings', [AdminCourseOfferingController::class, 'store']);
        Route::get('/offerings/{offering}', [AdminCourseOfferingController::class, 'show']);
        Route::patch('/offerings/{offering}', [AdminCourseOfferingController::class, 'update']);

        // Enrollments — /registrations is the review screen (grouped by
        // student); /enrollments is the flat resource.
        Route::get('/registrations', [RegistrationReviewController::class, 'index']);
        Route::patch('/registrations/bulk-review', [RegistrationReviewController::class, 'bulkReview']);

        Route::get('/enrollments/pending', [AdminEnrollmentController::class, 'pending']);
        Route::post('/enrollments', [AdminEnrollmentController::class, 'store']);
        Route::patch('/enrollments/{enrollment}/approve', [AdminEnrollmentController::class, 'approve']);
        Route::patch('/enrollments/{enrollment}/reject', [AdminEnrollmentController::class, 'reject']);
        Route::patch('/enrollments/{enrollment}/drop', [AdminEnrollmentController::class, 'drop']);

        // Grades — /results is the review screen (grouped by course offering).
        Route::get('/results', [ResultReviewController::class, 'index']);
        Route::patch('/results/bulk-review', [ResultReviewController::class, 'bulkReview']);
        Route::get('/results/{offering}', [ResultReviewController::class, 'show']);

        Route::get('/grades', [AdminGradeController::class, 'index']);
        Route::get('/grades/pending', [AdminGradeController::class, 'pending']);
        Route::patch('/grades/{grade}/review', [AdminGradeController::class, 'review']);

        // Academic sessions — no destroy route: offerings and GPA records
        // reference a session with restrictOnDelete, so one that anything ran
        // in cannot be removed. Promoting a session is its own endpoint rather
        // than a field on update, because it moves the whole portal.
        Route::get('/sessions', [AdminAcademicSessionController::class, 'index']);
        Route::post('/sessions', [AdminAcademicSessionController::class, 'store']);
        Route::get('/sessions/{session}', [AdminAcademicSessionController::class, 'show']);
        Route::patch('/sessions/{session}', [AdminAcademicSessionController::class, 'update']);
        Route::patch('/sessions/{session}/set-current', [AdminAcademicSessionController::class, 'setCurrent']);

        // Venues
        Route::get('/venues', [AdminVenueController::class, 'index']);
        Route::post('/venues', [AdminVenueController::class, 'store']);
        Route::get('/venues/{venue}', [AdminVenueController::class, 'show']);
        Route::patch('/venues/{venue}', [AdminVenueController::class, 'update']);

        // Timetable
        Route::get('/timetable', [AdminTimetableController::class, 'index']);
        Route::post('/timetable', [AdminTimetableController::class, 'store']);
        Route::get('/timetable/{slot}', [AdminTimetableController::class, 'show']);
        Route::patch('/timetable/{slot}', [AdminTimetableController::class, 'update']);
        Route::delete('/timetable/{slot}', [AdminTimetableController::class, 'destroy']);
    });
