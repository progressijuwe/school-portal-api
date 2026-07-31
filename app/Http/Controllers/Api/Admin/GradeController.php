<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GradeAuditAction;
use App\Enums\GradeStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\ApproveGradeRequest;
use App\Http\Resources\GradeResource;
use App\Jobs\RecomputeStudentGpa;
use App\Models\Grade;
use App\Notifications\GradeApprovedNotification;
use App\Notifications\GradeRejectedNotification;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends BaseController
{
    /**
     * @var array<int, string>
     */
    private const RESPONSE_RELATIONS = [
        'enrollment.student',
        'enrollment.courseOffering.course',
        'submittedBy',
        'approvedBy',
    ];

    public function __construct(protected GradeService $gradeService) {}

    public function index(Request $request): JsonResponse
    {
        $grades = Grade::with(self::RESPONSE_RELATIONS)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->latest()
            ->paginate(20);

        return $this->paginated($grades, GradeResource::class, 'Grades retrieved successfully.');
    }

    public function pending(): JsonResponse
    {
        $grades = Grade::with(self::RESPONSE_RELATIONS)
            ->where('status', GradeStatus::Pending)
            ->latest()
            ->paginate(20);

        return $this->paginated($grades, GradeResource::class, 'Pending grades retrieved successfully.');
    }

    /**
     * Approve or reject a submitted grade.
     *
     * The status change and the audit entry are one atomic unit; the
     * notification and the GPA recompute run only after that unit commits, so a
     * failure part-way through can no longer leave a student with an approved
     * grade, a notification and a stale CGPA.
     */
    public function review(ApproveGradeRequest $request, Grade $grade): JsonResponse
    {
        $this->authorize('review', $grade);

        return $request->string('action')->toString() === 'approve'
            ? $this->approve($request, $grade)
            : $this->reject($request, $grade);
    }

    private function approve(ApproveGradeRequest $request, Grade $grade): JsonResponse
    {
        $studentId = DB::transaction(function () use ($request, $grade) {
            $before = $grade->only(['status', 'approved_by', 'approved_at']);

            $grade->update([
                'status' => GradeStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $this->gradeService->audit(
                $grade,
                $request->user(),
                GradeAuditAction::Approved,
                $before,
                ipAddress: $request->ip(),
            );

            return $grade->enrollment->student_id;
        });

        $grade->load(self::RESPONSE_RELATIONS);

        $grade->enrollment->student->notify(new GradeApprovedNotification($grade));
        RecomputeStudentGpa::dispatch($studentId);

        return response()->json([
            'success' => true,
            'message' => 'Grade approved successfully.',
            'data' => new GradeResource($grade),
        ]);
    }

    private function reject(ApproveGradeRequest $request, Grade $grade): JsonResponse
    {
        $reason = $request->string('rejection_reason')->toString();

        DB::transaction(function () use ($request, $grade, $reason) {
            $before = $grade->only(['status', 'rejection_reason']);

            $grade->update([
                'status' => GradeStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->gradeService->audit(
                $grade,
                $request->user(),
                GradeAuditAction::Rejected,
                $before,
                reason: $reason,
                ipAddress: $request->ip(),
            );
        });

        $grade->load(self::RESPONSE_RELATIONS);

        $grade->submittedBy->notify(new GradeRejectedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade rejected.',
            'data' => new GradeResource($grade),
        ]);
    }
}
