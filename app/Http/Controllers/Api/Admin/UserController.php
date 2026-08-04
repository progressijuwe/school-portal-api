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
use Illuminate\Database\Eloquent\Builder;
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

        $users = $this->filteredUsers($request, $role)
            ->latest()
            // Honours per_page so a picker can ask for more than one table page
            // of lecturers in a single request, capped so a caller cannot ask
            // for the whole user table.
            ->paginate(perPage: min($request->integer('per_page', 20), 100))
            ->withQueryString();

        return $this->paginated($users, UserResource::class, 'Users retrieved successfully.');
    }

    /**
     * The filter chain the table and the export both run through.
     *
     * Shared so an export cannot quietly return a different set of people from
     * the list the administrator was looking at when they pressed the button.
     *
     * @return Builder<User>
     */
    private function filteredUsers(Request $request, string $role): Builder
    {
        // The lecturers table has a "Courses" column. Scoped to the session the
        // request is about so the number agrees with the count on the
        // lecturer's own dashboard — an unscoped count would total every
        // offering they have ever run and report 29 against the dashboard's 9.
        $sessionId = $role === 'lecturer'
            ? $this->resolveSession($request)?->id
            : null;

        return User::query()
            ->with('department.faculty', 'lecturerProfile', 'profile')
            ->when($sessionId !== null, fn ($query) => $query
                ->withCount(['taughtOfferings' => fn ($offerings) => $offerings
                    ->where('academic_session_id', $sessionId)]))
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
            // Lets an admin jump straight to the people who are locked out,
            // which is the whole point of recording the request.
            ->when($request->boolean('reset_requested'), fn ($query) => $query
                ->whereNotNull('password_reset_requested_at'));
    }

    /**
     * Export the current list as CSV.
     *
     * Streams every row the filters match rather than the page on screen: an
     * export that silently stopped at twenty would be worse than no export,
     * because nothing about the resulting file says it is partial.
     *
     * Chunked so the response does not depend on the whole cohort fitting in
     * memory at once.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $role = $request->string('role', 'student')->toString();

        if (! in_array($role, ['student', 'lecturer'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Role must be either student or lecturer.',
            ], 422);
        }

        $isStudent = $role === 'student';

        $headers = $isStudent
            ? ['student_id', 'name', 'email', 'phone', 'department', 'faculty', 'level', 'entry_year', 'study_type', 'registered_on']
            : ['staff_id', 'name', 'email', 'phone', 'department', 'faculty', 'prefix', 'highest_qualification', 'specialization', 'registered_on'];

        $query = $this->filteredUsers($request, $role)->orderBy('name');
        $filename = $role.'s_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $headers, $isStudent) {
            $handle = fopen('php://output', 'w');

            // Excel assumes the system codepage without a BOM, which mangles
            // any non-ASCII name in the export.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            $query->chunk(200, function ($users) use ($handle, $isStudent) {
                foreach ($users as $user) {
                    fputcsv($handle, $isStudent
                        ? [
                            $user->student_id,
                            $user->name,
                            $user->email,
                            $user->profile?->phone,
                            $user->department?->name,
                            $user->department?->faculty?->name,
                            $user->level,
                            $user->entry_year,
                            $user->study_type,
                            $user->created_at?->toDateString(),
                        ]
                        : [
                            $user->staff_id,
                            $user->name,
                            $user->email,
                            $user->profile?->phone,
                            $user->department?->name,
                            $user->department?->faculty?->name,
                            $user->lecturerProfile?->prefix,
                            $user->lecturerProfile?->highest_qualification,
                            $user->lecturerProfile?->specialization,
                            $user->created_at?->toDateString(),
                        ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The temporary password is returned to the administrator who created the
     * account.
     *
     * It used to be generated, emailed, and dropped from the response. With no
     * mail service configured that made every new account unreachable: nobody
     * ever learned the password, and there was no reset path either. Handing it
     * back is the only delivery channel that exists, so the admin can pass it on
     * directly.
     */
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
            'data' => [
                // resolve(), not toArray(). toArray() returns the raw array
                // including the MissingValue placeholders that `when()` yields
                // for absent fields; it is resolve() that strips them. Spreading
                // toArray() serialised every inapplicable field — a student's
                // staff_id, an unloaded phone and address — as `{}`.
                ...(new UserResource($result['user']))->resolve($request),
                'temporary_password' => $result['password'],
            ],
        ], 201);
    }

    /**
     * Issue a new temporary password for a user who cannot get in.
     *
     * Admins are excluded: an admin resetting another admin's password is a
     * privilege-escalation path, and the seeded super admin is recovered from
     * the console rather than through the portal.
     */
    public function resetPassword(User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator passwords cannot be reset from the portal.',
            ], 403);
        }

        $temporaryPassword = $this->userService->resetPassword($user);

        return response()->json([
            'success' => true,
            'message' => 'A new temporary password has been issued.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'temporary_password' => $temporaryPassword,
            ],
        ]);
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
                $imported = $this->userService->createUser($row);

                // Each account's temporary password travels back with it, for
                // the same reason single creation returns one: there is no mail
                // service, so an import that withheld them would produce a
                // whole cohort of accounts nobody could sign in to.
                $created[] = [
                    // resolve() rather than toArray(), for the reason given on
                    // the single-creation response above.
                    ...(new UserResource($imported['user']))->resolve($request),
                    'temporary_password' => $imported['password'],
                ];
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
