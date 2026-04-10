<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Notifications\GradeApprovedNotification;
use App\Notifications\GradeRejectedNotification;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    public function __construct(protected GradeService $gradeService) {}

    public function index(): JsonResponse
    {
        $grades = Grade::with([
                'enrollment.student',
                'enrollment.courseOffering.course',
                'submittedBy',
                'approvedBy',
            ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Grades retrieved successfully.',
            'data'    => GradeResource::collection($grades->items()),
            'meta'    => [
                'current_page' => $grades->currentPage(),
                'last_page'    => $grades->lastPage(),
                'per_page'     => $grades->perPage(),
                'total'        => $grades->total(),
            ],
        ]);
    }

    public function pending(): JsonResponse
    {
        $grades = Grade::with([
                'enrollment.student',
                'enrollment.courseOffering.course',
                'submittedBy',
            ])
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Pending grades retrieved successfully.',
            'data'    => GradeResource::collection($grades->items()),
            'meta'    => [
                'current_page' => $grades->currentPage(),
                'last_page'    => $grades->lastPage(),
                'per_page'     => $grades->perPage(),
                'total'        => $grades->total(),
            ],
        ]);
    }

    public function review(ApproveGradeRequest $request, Grade $grade): JsonResponse
    {
        if ($grade->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'This grade has already been approved.',
            ], 409);
        }

        if ($request->action === 'approve') {
            $grade->update([
                'status'      => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $grade->load(
                'enrollment.student',
                'enrollment.courseOffering.course',
                'submittedBy',
                'approvedBy'
            );

            // Notify student
            $grade->enrollment->student->notify(new GradeApprovedNotification($grade));

            // Recompute GPA
            $offering = $grade->enrollment->courseOffering;
            $this->gradeService->computeAndStoreGpa(
                $grade->enrollment->student_id,
                $offering->academic_session_id,
                $offering->semester
            );

            return response()->json([
                'success' => true,
                'message' => 'Grade approved successfully.',
                'data'    => new GradeResource($grade),
            ]);
        }

        // Reject
        $grade->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by'      => null,
            'approved_at'      => null,
        ]);

        $grade->load(
            'enrollment.student',
            'enrollment.courseOffering.course',
            'submittedBy'
        );

        // Notify lecturer
        $grade->submittedBy->notify(new GradeRejectedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade rejected.',
            'data'    => new GradeResource($grade),
        ]);
    }
}