<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\Semester;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\BulkImportUsersRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\GradeResource;
use App\Http\Resources\UserResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService,
        protected CsvImportService $csvImportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $role = $request->string('role', 'student')->toString();

        if (! in_array($role, ['student', 'lecturer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Role must be either student or lecturer.',
            ], 422);
        }

        $users = User::query()
            ->with('department.faculty', 'lecturerProfile', 'profile')
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('staff_id', 'like', "%{$search}%"));
            })
            ->when($request->filled('faculty_id'), fn ($query) => $query
                ->whereHas('department', fn ($dept) => $dept
                    ->where('faculty_id', $request->integer('faculty_id'))))
            ->when($request->filled('department_id'), fn ($query) => $query
                ->where('department_id', $request->integer('department_id')))
            ->when($role === 'student' && $request->filled('level'), fn ($query) => $query
                ->atLevel($request->integer('level')))
            ->when($role === 'student' && $request->filled('entry_year'), fn ($query) => $query
                ->where('entry_year', $request->integer('entry_year')))
            ->latest()
            // Honours per_page so a picker can ask for more than one table page
            // of lecturers in a single request, capped so a caller cannot ask
            // for the whole user table.
            ->paginate(perPage: min($request->integer('per_page', 20), 100))
            ->withQueryString();

        return $this->paginated($users, UserResource::class, 'Users retrieved successfully.');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->userService->createUser(
            $request->validated(),
            $request->file('photo')
        );

        $result['user']->load('department.faculty', 'profile', 'lecturerProfile');

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->string('role')->toString()).' account created successfully.',
            'data' => new UserResource($result['user']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('department.faculty', 'profile', 'lecturerProfile');

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Correct a user's details.
     *
     * The lecturer profile fields live on a separate table, so they are split
     * out of the payload and upserted alongside the user row in one transaction.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $lecturerFields = array_intersect_key($data, array_flip([
            'prefix', 'highest_qualification', 'specialization',
        ]));

        $userFields = array_diff_key($data, $lecturerFields);

        DB::transaction(function () use ($user, $userFields, $lecturerFields) {
            if ($userFields !== []) {
                $user->update($userFields);
            }

            if ($lecturerFields !== [] && $user->hasRole('lecturer')) {
                $user->lecturerProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $lecturerFields
                );
            }
        });

        $user->load('department.faculty', 'profile', 'lecturerProfile');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Archive a user.
     *
     * A soft delete, not a hard one: every foreign key into users is
     * restrictOnDelete, so removing the row would either fail outright or
     * orphan a transcript. The account stops working immediately — tokens are
     * revoked here rather than waiting for them to expire.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator accounts cannot be removed from here.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return $this->ok('User archived successfully.');
    }

    /**
     * Bulk-create users from a CSV.
     *
     * Each row is its own transaction so one bad record does not discard the
     * whole import — the response reports created and failed rows separately.
     */
    public function bulkImport(BulkImportUsersRequest $request): JsonResponse
    {
        $role = $request->string('role')->toString();
        $result = $this->csvImportService->parse($request->file('file'), $role);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        $created = [];
        $failed = $result['invalid'];

        foreach ($result['valid'] as $row) {
            $row['role'] = $role;

            try {
                $created[] = new UserResource($this->userService->createUser($row)['user']);
            } catch (\Throwable $e) {
                report($e);

                $failed[] = [
                    'row' => $row['__row'] ?? null,
                    'data' => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($created).' user(s) imported successfully.',
            'data' => [
                'created' => $created,
                'failed' => array_values($failed),
            ],
        ], 201);
    }

    /**
     * Streams a real text/csv download rather than base64 inside a JSON body,
     * which the browser could not save without client-side decoding.
     */
    public function downloadCsvTemplate(string $role): StreamedResponse|JsonResponse
    {
        if (! in_array($role, ['student', 'lecturer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role. Must be student or lecturer.',
            ], 422);
        }

        [$headers, $example] = $role === 'student'
            ? [
                ['name', 'email', 'department_id', 'study_type', 'entry_year'],
                ['John Doe', 'john@uni.edu', '1', 'Undergraduate', '2022'],
            ]
            : [
                ['name', 'email', 'department_id', 'prefix', 'highest_qualification', 'specialization'],
                ['Jane Smith', 'jane@uni.edu', '1', 'Dr.', 'PhD Computer Science', 'Artificial Intelligence'],
            ];

        return response()->streamDownload(function () use ($headers, $example) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $example);
            fclose($handle);
        }, "{$role}_import_template.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
        // The fourth parameter is $disposition (a Content-Disposition string),
        // not a status code. Passing 200 there emitted `Content-Disposition: 200`
        // and the browser had no filename to save under. The default,
        // "attachment", is what is wanted here.
    }

    /**
     * Headline figures for the admin's profile drawer.
     */
    public function summary(User $user): JsonResponse
    {
        if ($user->hasRole('student')) {
            return $this->studentSummary($user);
        }

        if ($user->hasRole('lecturer')) {
            return $this->lecturerSummary($user);
        }

        return response()->json([
            'success' => false,
            'message' => 'Summary not available for this user role.',
        ], 422);
    }

    /**
     * Pass/fail counts are aggregated in the database. The previous version
     * hydrated every graded enrollment with its grade and filtered in PHP.
     */
    private function studentSummary(User $user): JsonResponse
    {
        $latestGpa = GpaRecord::where('student_id', $user->id)->latest('id')->first();

        $counts = Enrollment::query()
            ->where('enrollments.student_id', $user->id)
            ->join('grades', 'grades.enrollment_id', '=', 'enrollments.id')
            ->where('grades.status', GradeStatus::Approved->value)
            ->selectRaw('SUM(CASE WHEN grades.grade_point > 0 THEN 1 ELSE 0 END) as passed')
            ->selectRaw('SUM(CASE WHEN grades.grade_point = 0 THEN 1 ELSE 0 END) as failed')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Student summary retrieved successfully.',
            'data' => [
                'cgpa' => $latestGpa->cgpa ?? '0.00',
                'total_credits' => $latestGpa->cumulative_credit_units ?? 0,
                'passed_courses' => (int) ($counts->passed ?? 0),
                'failed_courses' => (int) ($counts->failed ?? 0),
            ],
        ]);
    }

    private function lecturerSummary(User $user): JsonResponse
    {
        $offeringIds = CourseOffering::where('lecturer_id', $user->id)->pluck('id');

        $totalStudents = Enrollment::whereIn('course_offering_id', $offeringIds)
            ->where('status', EnrollmentStatus::Active)
            ->count();

        $totalCourses = $offeringIds->count();

        return response()->json([
            'success' => true,
            'message' => 'Lecturer summary retrieved successfully.',
            'data' => [
                'department_code' => $user->department->code ?? 'N/A',
                'total_courses' => $totalCourses,
                'total_students' => $totalStudents,
                'average_students' => $totalCourses > 0 ? (int) round($totalStudents / $totalCourses) : 0,
            ],
        ]);
    }

    /**
     * A student's grades for one session/semester, as seen by an admin.
     */
    public function studentGrades(User $user, Request $request): JsonResponse
    {
        if (! $user->hasRole('student')) {
            return response()->json(['success' => false, 'message' => 'User is not a student.'], 422);
        }

        $semester = $this->resolveSemester($request, $this->resolveSession($request));

        $enrollments = Enrollment::where('student_id', $user->id)
            ->whereHas('courseOffering', function ($query) use ($request, $semester) {
                if ($request->filled('session_id')) {
                    $query->where('academic_session_id', $request->integer('session_id'));
                }

                $query->where('semester', $semester);
            })
            ->with([
                'courseOffering.course',
                'grade' => fn ($query) => $query->where('status', GradeStatus::Approved->value),
            ])
            ->get();

        $grades = $enrollments->map(fn (Enrollment $enrollment) => [
            'course' => [
                'title' => $enrollment->courseOffering->course->title,
                'code' => $enrollment->courseOffering->course->code,
                'credit_units' => $enrollment->courseOffering->course->credit_units,
            ],
            'grade' => $enrollment->grade ? new GradeResource($enrollment->grade) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student grades retrieved successfully.',
            'data' => ['grades' => $grades],
        ]);
    }

    /**
     * A lecturer's assigned offerings for a session, as seen by an admin.
     */
    public function lecturerCourses(User $user, Request $request): JsonResponse
    {
        if (! $user->hasRole('lecturer')) {
            return response()->json(['success' => false, 'message' => 'User is not a lecturer.'], 422);
        }

        $offerings = CourseOffering::where('lecturer_id', $user->id)
            ->withCount(['enrollments' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value)])
            ->with('course', 'academicSession')
            ->when($request->filled('session_id'), fn ($query) => $query
                ->where('academic_session_id', $request->integer('session_id')))
            ->get();

        $courses = $offerings->map(fn (CourseOffering $offering) => [
            'offering' => new CourseOfferingResource($offering),
            'enrolled_count' => $offering->enrollments_count,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lecturer courses retrieved successfully.',
            'data' => ['courses' => $courses],
        ]);
    }
}
