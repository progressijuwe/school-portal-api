<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Enums\GradeStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Lecturer\BatchSubmitGradeRequest;
use App\Http\Requests\Lecturer\SaveDraftGradeRequest;
use App\Http\Requests\Lecturer\SubmitGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use App\Notifications\GradeSubmittedNotification;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GradeController extends BaseController
{
    /**
     * Relations every grade response needs. Named once so no endpoint
     * accidentally serialises a partially loaded resource.
     *
     * @var array<int, string>
     */
    private const RESPONSE_RELATIONS = [
        'enrollment.student',
        'enrollment.courseOffering.course',
        'submittedBy',
    ];

    public function __construct(protected GradeService $gradeService) {}

    public function index(Request $request): JsonResponse
    {
        $grades = Grade::with(self::RESPONSE_RELATIONS)
            ->where('submitted_by', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->paginated($grades, GradeResource::class, 'Grades retrieved successfully.');
    }

    /**
     * Single submit — used to resubmit one row after a rejection.
     */
    public function submit(SubmitGradeRequest $request): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($request->integer('enrollment_id'));

        $grade = DB::transaction(fn () => $this->gradeService->persist(
            $enrollment,
            $request->safe()->only(['ca_score', 'project_score', 'exam_score']),
            GradeStatus::Pending,
            $request->user(),
            $request->ip(),
        ));

        $grade->load(self::RESPONSE_RELATIONS);
        $this->notifyAdmins($grade, 1);

        return response()->json([
            'success' => true,
            'message' => 'Grade submitted successfully and is pending approval.',
            'data' => new GradeResource($grade),
        ], 201);
    }

    /**
     * Batch submit — "Submit Results" on the lecturer results page.
     */
    public function batchSubmit(BatchSubmitGradeRequest $request): JsonResponse
    {
        $submitted = $this->persistBatch($request, GradeStatus::Pending);

        $this->notifyAdmins($submitted->first(), $submitted->count());

        return response()->json([
            'success' => true,
            'message' => "{$submitted->count()} grades submitted successfully and are pending approval.",
            'data' => GradeResource::collection($submitted),
        ], 201);
    }

    /**
     * Batch save as draft — no letter grade is resolved and no one is notified.
     */
    public function saveDraft(SaveDraftGradeRequest $request): JsonResponse
    {
        $saved = $this->persistBatch($request, GradeStatus::Draft);

        return response()->json([
            'success' => true,
            'message' => "{$saved->count()} draft grades saved.",
            'data' => GradeResource::collection($saved),
        ]);
    }

    /**
     * Amend a previously submitted grade.
     *
     * The route-bound grade is the record that gets written — the previous
     * version authorized `$grade` and then wrote to whatever `enrollment_id`
     * the request body carried, which made the ownership check decorative.
     */
    public function update(SubmitGradeRequest $request, Grade $grade): JsonResponse
    {
        $this->authorize('update', $grade);

        $updated = DB::transaction(fn () => $this->gradeService->persist(
            $grade->enrollment,
            $request->safe()->only(['ca_score', 'project_score', 'exam_score']),
            GradeStatus::Pending,
            $request->user(),
            $request->ip(),
        ));

        $updated->load(self::RESPONSE_RELATIONS);
        $this->notifyAdmins($updated, 1);

        return response()->json([
            'success' => true,
            'message' => 'Grade updated and resubmitted for approval.',
            'data' => new GradeResource($updated),
        ]);
    }

    /**
     * Persist a whole mark sheet atomically.
     *
     * Previously each row was written in its own implicit transaction, so a
     * failure on row 20 of 40 left half a class graded with no error surfaced
     * for the rows that did land.
     *
     * @return Collection<int, Grade>
     */
    private function persistBatch(
        BatchSubmitGradeRequest|SaveDraftGradeRequest $request,
        GradeStatus $status,
    ): Collection {
        $entries = collect($request->validated()['grades']);

        $enrollments = Enrollment::whereIn('id', $entries->pluck('enrollment_id'))
            ->get()
            ->keyBy('id');

        $lecturer = $request->user();
        $ip = $request->ip();

        $grades = DB::transaction(fn () => $entries->map(fn (array $entry) => $this->gradeService->persist(
            $enrollments[$entry['enrollment_id']],
            [
                'ca_score' => $entry['ca_score'] ?? null,
                'project_score' => $entry['project_score'] ?? null,
                'exam_score' => $entry['exam_score'] ?? null,
            ],
            $status,
            $lecturer,
            $ip,
        )));

        return $grades->each->load(self::RESPONSE_RELATIONS);
    }

    /**
     * Notify admins once per submission, not once per row.
     */
    private function notifyAdmins(?Grade $grade, int $count): void
    {
        if ($grade === null) {
            return;
        }

        Notification::send(
            User::role('admin')->get(),
            new GradeSubmittedNotification($grade, $count)
        );
    }
}
