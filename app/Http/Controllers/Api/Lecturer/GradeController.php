<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\SubmitGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\GradeSubmittedNotification;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class GradeController extends Controller
{
    public function __construct(protected GradeService $gradeService) {}

    public function index(Request $request): JsonResponse
    {
        $grades = Grade::with([
                'enrollment.student',
                'enrollment.courseOffering.course',
            ])
            ->where('submitted_by', $request->user()->id)
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

    public function submit(SubmitGradeRequest $request): JsonResponse
    {
        $resolved   = $this->gradeService->resolveGrade($request->score);
        $enrollment = Enrollment::find($request->enrollment_id);

        // Upsert — allows resubmission if previously rejected
        $grade = Grade::updateOrCreate(
            ['enrollment_id' => $request->enrollment_id],
            [
                'submitted_by' => $request->user()->id,
                'score'        => $request->score,
                'letter_grade' => $resolved['letter_grade'],
                'grade_point'  => $resolved['grade_point'],
                'status'       => 'pending',
                'rejection_reason' => null,
                'submitted_at' => now(),
                'approved_at'  => null,
                'approved_by'  => null,
            ]
        );

        $grade->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        // Notify all admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new GradeSubmittedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade submitted successfully and is pending approval.',
            'data'    => new GradeResource($grade),
        ], 201);
    }

    public function update(SubmitGradeRequest $request, Grade $grade): JsonResponse
    {
        // Only allow updates on pending or rejected grades
        if ($grade->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update an approved grade.',
            ], 409);
        }

        // Make sure the lecturer owns this grade
        if ($grade->submitted_by !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this grade.',
            ], 403);
        }

        $resolved = $this->gradeService->resolveGrade($request->score);

        $grade->update([
            'score'            => $request->score,
            'letter_grade'     => $resolved['letter_grade'],
            'grade_point'      => $resolved['grade_point'],
            'status'           => 'pending',
            'rejection_reason' => null,
            'submitted_at'     => now(),
            'approved_at'      => null,
            'approved_by'      => null,
        ]);

        $grade->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        // Re-notify admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new GradeSubmittedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade updated and resubmitted for approval.',
            'data'    => new GradeResource($grade),
        ]);
    }
}