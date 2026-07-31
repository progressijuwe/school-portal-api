<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GradeAuditAction;
use App\Enums\GradeStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\BulkReviewGradesRequest;
use App\Http\Resources\GradeResource;
use App\Jobs\RecomputeStudentGpa;
use App\Models\CourseOffering;
use App\Models\Grade;
use App\Notifications\GradeApprovedNotification;
use App\Notifications\GradeRejectedNotification;
use App\Services\GradeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Results review, grouped by course offering.
 *
 * A lecturer submits a whole mark sheet, and the admin approves or rejects it
 * as a unit — so the review screen is one row per offering, not per grade.
 */
class ResultReviewController extends BaseController
{
    public function __construct(protected GradeService $gradeService) {}

    public function index(Request $request): JsonResponse
    {
        $status = GradeStatus::tryFrom((string) $request->query('status'))
            ?? GradeStatus::Pending;

        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $semester = $this->resolveSemester($request, $session);

        $offerings = CourseOffering::query()
            ->select('course_offerings.*')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->where('course_offerings.academic_session_id', $session->id)
            ->where('course_offerings.semester', $semester)
            ->whereHas('enrollments.grade', fn ($query) => $query->where('grades.status', $status->value))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn ($inner) => $inner
                    ->where('courses.code', 'like', "%{$search}%")
                    ->orWhere('courses.title', 'like', "%{$search}%"));
            })
            ->when($request->filled('department_id'), fn ($query) => $query
                ->where('courses.department_id', $request->integer('department_id')))
            ->when($request->filled('faculty_id'), fn ($query) => $query
                ->join('departments', 'departments.id', '=', 'courses.department_id')
                ->where('departments.faculty_id', $request->integer('faculty_id')))
            ->when($request->filled('level'), fn ($query) => $query
                ->where('courses.level', $request->string('level')->toString()))
            ->with(['course.department.faculty', 'lecturer.lecturerProfile'])
            ->orderBy('courses.code')
            ->paginate(8)
            ->withQueryString();

        $stats = $this->gradeStatsFor(
            collect($offerings->items())->pluck('id')->all(),
            $status,
        );

        $rows = collect($offerings->items())->map(function (CourseOffering $offering) use ($stats, $status) {
            $stat = $stats[$offering->id] ?? null;
            $prefix = $offering->lecturer?->lecturerProfile?->prefix;

            return [
                'id' => $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'lecturer' => $offering->lecturer
                    ? trim(($prefix ? $prefix.' ' : '').$offering->lecturer->name)
                    : 'Unassigned',
                'department' => $offering->course->department->name,
                'faculty' => $offering->course->department->faculty->name,
                'level' => (string) $offering->course->level,
                'students' => (int) ($stat->total ?? 0),
                'avgScore' => round((float) ($stat->average ?? 0), 1),
                'status' => $status->value,
                'grade_ids' => explode(',', (string) ($stat->grade_ids ?? '')),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Results retrieved successfully.',
            'data' => $rows,
            'meta' => [
                'current_page' => $offerings->currentPage(),
                'last_page' => $offerings->lastPage(),
                'per_page' => $offerings->perPage(),
                'total' => $offerings->total(),
                'counts' => $this->statusCounts($session->id, $semester),
            ],
        ]);
    }

    /**
     * Student count, mean score and grade ids per offering, in one query.
     *
     * @param  array<int, int>  $offeringIds
     * @return array<int, object>
     */
    private function gradeStatsFor(array $offeringIds, GradeStatus $status): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return Grade::query()
            ->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')
            ->whereIn('enrollments.course_offering_id', $offeringIds)
            ->where('grades.status', $status->value)
            ->groupBy('enrollments.course_offering_id')
            ->select('enrollments.course_offering_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(grades.score) as average')
            ->selectRaw('GROUP_CONCAT(grades.id) as grade_ids')
            ->get()
            ->keyBy('course_offering_id')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $sessionId, string $semester): array
    {
        $rows = Grade::query()
            ->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('course_offerings.academic_session_id', $sessionId)
            ->where('course_offerings.semester', $semester)
            ->groupBy('grades.status')
            ->select('grades.status')
            ->selectRaw('COUNT(DISTINCT enrollments.course_offering_id) as total')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($rows[GradeStatus::Pending->value] ?? 0),
            'approved' => (int) ($rows[GradeStatus::Approved->value] ?? 0),
            'rejected' => (int) ($rows[GradeStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * Show every grade in one offering — the "View details" drawer.
     */
    public function show(Request $request, CourseOffering $offering): JsonResponse
    {
        $status = GradeStatus::tryFrom((string) $request->query('status'));

        $grades = Grade::with(['enrollment.student', 'enrollment.courseOffering.course', 'submittedBy'])
            ->whereHas('enrollment', fn ($query) => $query->where('course_offering_id', $offering->id))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Offering results retrieved successfully.',
            'data' => GradeResource::collection($grades),
        ]);
    }

    /**
     * Approve or reject a whole mark sheet.
     *
     * The status change and audit trail commit together; notifications and GPA
     * recomputes are dispatched afterwards, one recompute per affected student
     * rather than one per grade.
     */
    public function bulkReview(BulkReviewGradesRequest $request): JsonResponse
    {
        $ids = $request->validated()['grade_ids'];
        $approve = $request->validated()['action'] === 'approve';
        $reason = $request->input('rejection_reason');
        $actor = $request->user();

        $grades = Grade::with('enrollment.student', 'submittedBy')
            ->whereIn('id', $ids)
            ->where('status', GradeStatus::Pending)
            ->get();

        if ($grades->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Only grades awaiting approval can be reviewed.',
            ], 409);
        }

        DB::transaction(function () use ($grades, $approve, $reason, $actor, $request) {
            foreach ($grades as $grade) {
                $before = $grade->only(['status', 'rejection_reason', 'approved_by', 'approved_at']);

                $grade->update($approve
                    ? [
                        'status' => GradeStatus::Approved,
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]
                    : [
                        'status' => GradeStatus::Rejected,
                        'rejection_reason' => $reason,
                        'approved_by' => null,
                        'approved_at' => null,
                    ]);

                $this->gradeService->audit(
                    $grade,
                    $actor,
                    $approve ? GradeAuditAction::Approved : GradeAuditAction::Rejected,
                    $before,
                    reason: $approve ? null : $reason,
                    ipAddress: $request->ip(),
                );
            }
        });

        $this->dispatchSideEffects($grades, $approve);

        return $this->ok(
            $grades->count().' result(s) '.($approve ? 'approved' : 'rejected').' successfully.'
        );
    }

    /**
     * @param  Collection<int, Grade>  $grades
     */
    private function dispatchSideEffects($grades, bool $approve): void
    {
        if ($approve) {
            foreach ($grades as $grade) {
                $grade->enrollment->student->notify(new GradeApprovedNotification($grade));
            }

            // One recompute per student, not per grade.
            $grades->pluck('enrollment.student_id')
                ->unique()
                ->each(fn (int $studentId) => RecomputeStudentGpa::dispatch($studentId));

            return;
        }

        // The whole sheet came from one lecturer; tell them once.
        $grades->groupBy('submitted_by')->each(
            fn ($forLecturer) => $forLecturer->first()->submittedBy
                ?->notify(new GradeRejectedNotification($forLecturer->first()))
        );
    }
}
