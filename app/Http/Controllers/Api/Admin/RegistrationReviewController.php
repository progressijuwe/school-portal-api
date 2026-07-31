<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\BulkReviewEnrollmentsRequest;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Course registration review, grouped by student.
 *
 * The admin screen shows one row per student with all the courses they
 * registered for, and approves or rejects the whole submission. Enrollments are
 * stored one row per course, so grouping has to happen somewhere — doing it
 * here keeps pagination coherent (paginate students, then load their rows),
 * which client-side grouping over a paginated enrollment list cannot.
 */
class RegistrationReviewController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $status = EnrollmentStatus::tryFrom((string) $request->query('status'))
            ?? EnrollmentStatus::Pending;

        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $semester = $this->resolveSemester($request, $session);

        // Page over students first so a student's courses are never split
        // across two pages.
        $studentIds = Enrollment::query()
            ->select('enrollments.student_id')
            ->join('users', 'users.id', '=', 'enrollments.student_id')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('enrollments.status', $status->value)
            ->where('course_offerings.academic_session_id', $session->id)
            ->where('course_offerings.semester', $semester)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn ($inner) => $inner
                    ->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.student_id', 'like', "%{$search}%"));
            })
            ->when($request->filled('department_id'), fn ($query) => $query
                ->where('users.department_id', $request->integer('department_id')))
            ->when($request->filled('faculty_id'), fn ($query) => $query
                ->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('departments.faculty_id', $request->integer('faculty_id')))
            // This query starts from Enrollment, so User's `atLevel` scope is
            // not callable on it — the shared rule is read from the model.
            // This query starts from Enrollment, so User's `atLevel` scope is
            // not callable on it — the shared rule is read from the model.
            ->when($request->filled('level'), function ($query) use ($request) {
                [$operator, $entryYear] = User::levelConstraint($request->integer('level'));

                $query->where('users.entry_year', $operator, $entryYear);
            })
            ->groupBy('enrollments.student_id')
            ->orderByRaw('MIN(enrollments.created_at) DESC')
            ->paginate(perPage: 8, columns: ['enrollments.student_id']);

        $rows = $this->buildRows(
            collect($studentIds->items())->pluck('student_id')->all(),
            $status,
            $session->id,
            $semester,
        );

        return response()->json([
            'success' => true,
            'message' => 'Registrations retrieved successfully.',
            'data' => $rows,
            'meta' => [
                'current_page' => $studentIds->currentPage(),
                'last_page' => $studentIds->lastPage(),
                'per_page' => $studentIds->perPage(),
                'total' => $studentIds->total(),
                'counts' => $this->statusCounts($session->id, $semester),
            ],
        ]);
    }

    /**
     * One row per student, shaped exactly as the registrations table renders it.
     *
     * @param  array<int, int>  $studentIds
     */
    private function buildRows(array $studentIds, EnrollmentStatus $status, int $sessionId, string $semester): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        $students = User::with('department.faculty')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $enrollments = Enrollment::with('courseOffering.course')
            ->whereIn('student_id', $studentIds)
            ->where('status', $status)
            ->forPeriod($sessionId, $semester)
            ->get()
            ->groupBy('student_id');

        // Preserve the pagination order rather than the order rows came back.
        return collect($studentIds)
            ->map(function (int $studentId) use ($students, $enrollments, $status) {
                $student = $students[$studentId] ?? null;

                if ($student === null) {
                    return null;
                }

                $rows = $enrollments[$studentId] ?? collect();

                return [
                    'id' => $student->student_id,
                    'student_key' => $student->id,
                    'name' => $student->name,
                    'level' => $student->level,
                    'faculty' => $student->department?->faculty?->name,
                    'department' => $student->department?->name,
                    'status' => $status->value,
                    'enrollment_ids' => $rows->pluck('id')->all(),
                    'courses' => $rows->map(fn (Enrollment $enrollment) => [
                        'code' => $enrollment->courseOffering->course->code,
                        'title' => $enrollment->courseOffering->course->title,
                        'units' => $enrollment->courseOffering->course->credit_units,
                    ])->values(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Tab counts, distinct by student so they match the rows on screen.
     *
     * @return array<string, int>
     */
    private function statusCounts(int $sessionId, string $semester): array
    {
        $rows = Enrollment::query()
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('course_offerings.academic_session_id', $sessionId)
            ->where('course_offerings.semester', $semester)
            ->groupBy('enrollments.status')
            ->select('enrollments.status')
            ->selectRaw('COUNT(DISTINCT enrollments.student_id) as total')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($rows[EnrollmentStatus::Pending->value] ?? 0),
            'approved' => (int) ($rows[EnrollmentStatus::Active->value] ?? 0),
            'rejected' => (int) ($rows[EnrollmentStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * Approve or reject a student's whole submission in one transaction.
     *
     * Doing this row-by-row from the client would leave a partially approved
     * registration behind whenever a request failed midway.
     */
    public function bulkReview(BulkReviewEnrollmentsRequest $request): JsonResponse
    {
        $ids = $request->validated()['enrollment_ids'];
        $approve = $request->validated()['action'] === 'approve';
        $target = $approve ? EnrollmentStatus::Active : EnrollmentStatus::Rejected;

        $updated = DB::transaction(fn () => Enrollment::whereIn('id', $ids)
            ->where('status', EnrollmentStatus::Pending)
            ->update(['status' => $target, 'updated_at' => now()]));

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending registrations can be reviewed.',
            ], 409);
        }

        return $this->ok(
            $updated.' registration(s) '.($approve ? 'approved' : 'rejected').' successfully.'
        );
    }
}
