<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\SubmitGradeRequest;
use App\Http\Requests\Lecturer\SaveDraftGradeRequest;
use App\Http\Requests\Lecturer\BatchSubmitGradeRequest;
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

    // Single submit — kept for backward compatibility / single-row resubmission (e.g. after rejection)
    public function submit(SubmitGradeRequest $request): JsonResponse
    {
        $grade = $this->persistGrade($request->enrollment_id, $request->only(['ca_score', 'project_score', 'exam_score']), 'pending', $request->user()->id);

        $grade->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        $admins = User::role('admin')->get();
        Notification::send($admins, new GradeSubmittedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade submitted successfully and is pending approval.',
            'data'    => new GradeResource($grade),
        ], 201);
    }

    // Batch submit — used by "Submit Results" on the lecturer results page
    public function batchSubmit(BatchSubmitGradeRequest $request): JsonResponse
    {
        $lecturerId = $request->user()->id;
        $submitted  = collect();

        foreach ($request->grades as $entry) {
            $grade = $this->persistGrade(
                $entry['enrollment_id'],
                ['ca_score' => $entry['ca_score'], 'project_score' => $entry['project_score'], 'exam_score' => $entry['exam_score']],
                'pending',
                $lecturerId
            );
            $submitted->push($grade);
        }

        $submitted->each->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        // Notify admins once for the whole batch, not once per grade
        $admins = User::role('admin')->get();
        Notification::send($admins, new GradeSubmittedNotification($submitted->first(), $submitted->count()));

        return response()->json([
            'success' => true,
            'message' => "{$submitted->count()} grades submitted successfully and are pending approval.",
            'data'    => GradeResource::collection($submitted),
        ], 201);
    }

    // Batch save as draft — no validation guard on enrollment/grade state, no notification
    public function saveDraft(SaveDraftGradeRequest $request): JsonResponse
    {
        $lecturerId = $request->user()->id;
        $saved      = collect();

        foreach ($request->grades as $entry) {
            $grade = $this->persistGrade(
                $entry['enrollment_id'],
                [
                    'ca_score'      => $entry['ca_score'] ?? null,
                    'project_score' => $entry['project_score'] ?? null,
                    'exam_score'    => $entry['exam_score'] ?? null,
                ],
                'draft',
                $lecturerId
            );
            $saved->push($grade);
        }

        return response()->json([
            'success' => true,
            'message' => "{$saved->count()} draft grades saved.",
            'data'    => GradeResource::collection($saved),
        ]);
    }

    public function update(SubmitGradeRequest $request, Grade $grade): JsonResponse
    {
        if ($grade->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update an approved grade.',
            ], 409);
        }

        if ($grade->submitted_by !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this grade.',
            ], 403);
        }

        $grade = $this->persistGrade($request->enrollment_id, $request->only(['ca_score', 'project_score', 'exam_score']), 'pending', $request->user()->id);

        $grade->load('enrollment.student', 'enrollment.courseOffering.course', 'submittedBy');

        $admins = User::role('admin')->get();
        Notification::send($admins, new GradeSubmittedNotification($grade));

        return response()->json([
            'success' => true,
            'message' => 'Grade updated and resubmitted for approval.',
            'data'    => new GradeResource($grade),
        ]);
    }

    // Shared logic for computing total score + resolving letter grade, then upserting
    protected function persistGrade(int $enrollmentId, array $components, string $status, int $lecturerId): Grade
    {
        $caScore      = $components['ca_score'] ?? 0;
        $projectScore = $components['project_score'] ?? 0;
        $examScore    = $components['exam_score'] ?? 0;
        $totalScore   = $caScore + $projectScore + $examScore;

        $resolved = $status === 'pending'
            ? $this->gradeService->resolveGrade($totalScore)
            : ['letter_grade' => null, 'grade_point' => null]; // drafts don't get a letter grade yet

        return Grade::updateOrCreate(
            ['enrollment_id' => $enrollmentId],
            [
                'submitted_by'      => $lecturerId,
                'ca_score'          => $components['ca_score'] ?? null,
                'project_score'     => $components['project_score'] ?? null,
                'exam_score'        => $components['exam_score'] ?? null,
                'score'             => $totalScore,
                'letter_grade'      => $resolved['letter_grade'],
                'grade_point'       => $resolved['grade_point'],
                'status'            => $status,
                'rejection_reason'  => null,
                'submitted_at'      => $status === 'pending' ? now() : null,
                'approved_at'       => null,
                'approved_by'       => null,
            ]
        );
    }
}