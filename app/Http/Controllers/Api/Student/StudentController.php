<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\GradeStatus;
use App\Enums\Semester;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\GpaRecordResource;
use App\Http\Resources\GradeResource;
use App\Http\Resources\TimetableSlotResource;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends BaseController
{
    // Dashboard
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        // Enrolled courses count for current session
        $enrolledCount = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_session_id', $session->id)
            )
            ->count();

        /*
         * A semester the student is enrolled in but which has not been graded
         * yet still has a GPA record — recomputeStudentHistory creates one for
         * every period with enrollments, carrying zero credit units until marks
         * are approved.
         *
         * `total_credit_units > 0` is what separates "scored zero" from "not
         * scored yet". Without it the dashboard announced a GPA of 0.00 for the
         * semester in progress, and a change of -3.64 against the last graded
         * one — a student who had done nothing wrong reading that they had
         * fallen off a cliff.
         */
        $gradedGpa = fn ($query) => $query->where('total_credit_units', '>', 0);

        $firstSemesterGpa = GpaRecord::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('semester', 'first')
            ->tap($gradedGpa)
            ->first();

        $secondSemesterGpa = GpaRecord::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('semester', 'second')
            ->tap($gradedGpa)
            ->first();

        // Overall CGPA — latest graded record has the most up to date CGPA.
        $recentGpas = GpaRecord::where('gpa_records.student_id', $student->id)
            ->where('gpa_records.total_credit_units', '>', 0)
            ->join('academic_sessions', 'academic_sessions.id', '=', 'gpa_records.academic_session_id')
            ->orderByDesc('academic_sessions.start_year')
            ->orderByRaw("FIELD(gpa_records.semester, 'second', 'first')")
            ->limit(2)
            ->select('gpa_records.*')
            ->get();

        $latestGpa = $recentGpas->first();
        $previousGpa = $recentGpas->count() >= 2 ? $recentGpas[1] : null;

        $gpaChange = ($latestGpa && $previousGpa)
            ? round($latestGpa->gpa - $previousGpa->gpa, 2)
            : null;

        $cgpaChange = ($latestGpa && $previousGpa)
            ? round($latestGpa->cgpa - $previousGpa->cgpa, 2)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Dashboard retrieved successfully.',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'student_id' => $student->student_id,
                    'department' => $student->department?->name,
                    'level' => $this->resolveLevel($student),
                    'study_type' => $student->study_type,
                    'entry_year' => $student->entry_year,
                    'graduation_year' => $student->entry_year && $student->department
                        ? $student->entry_year + $student->department->duration_years
                        : null,
                ],
                'session' => [
                    'id' => $session->id,
                    'name' => $session->name,
                    'is_current' => $session->is_current,
                ],
                'enrolled_courses' => $enrolledCount,
                'first_semester_gpa' => $firstSemesterGpa?->gpa,
                'second_semester_gpa' => $secondSemesterGpa?->gpa,
                'cgpa' => $latestGpa->cgpa ?? '0.00',
                'cumulative_credit_units' => $latestGpa->cumulative_credit_units ?? 0,
                'gpa_change' => $gpaChange,
                'cgpa_change' => $cgpaChange,
            ],
        ]);
    }

    // Courses

    public function courses(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_session_id', $session->id)
            )
            ->with([
                'courseOffering.course.department',
                'courseOffering.lecturer',
                'courseOffering.academicSession',
            ])
            ->get();

        $courses = $enrollments->map(fn ($enrollment) => [
            'enrollment_id' => $enrollment->id,
            'status' => $enrollment->status,
            'offering' => new CourseOfferingResource($enrollment->courseOffering),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully.',
            'data' => [
                'session' => $session->name,
                'courses' => $courses,
            ],
        ]);
    }

    // Timetable

    public function timetable(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        // Get all course offering IDs the student is enrolled in
        $offeringIds = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_session_id', $session->id)
            )
            ->pluck('course_offering_id');

        $slots = TimetableSlot::whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->with('courseOffering.course', 'courseOffering.lecturer', 'venue')
            ->orderByRaw("CASE day
                WHEN 'monday'    THEN 1
                WHEN 'tuesday'   THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday'  THEN 4
                WHEN 'friday'    THEN 5
            END")
            ->orderBy('start_time')
            ->get();

        // Group by day for easier frontend rendering
        $grouped = $slots->groupBy('day')->map(fn ($daySlots) => TimetableSlotResource::collection($daySlots)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable retrieved successfully.',
            'data' => [
                'session' => [
                    'name' => $session->name,
                    'first_semester_start' => $session->first_semester_start?->toDateString(),
                    'first_semester_end' => $session->first_semester_end?->toDateString(),
                    'second_semester_start' => $session->second_semester_start?->toDateString(),
                    'second_semester_end' => $session->second_semester_end?->toDateString(),
                ],
                'timetable' => $grouped,
            ],
        ]);
    }

    // Grades

    public function grades(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        // Was `$request->query('semester', 'first')`, the one endpoint that did
        // not go through the shared resolver — so a student who opened Results
        // without picking a semester saw an empty first-semester table all
        // through the second half of the year.
        $semester = $this->resolveSemester($request, $session);

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_session_id', $session->id)
                ->where('semester', $semester)
            )
            ->with([
                'courseOffering.course',
                'courseOffering.academicSession',
                'grade' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->get();

        $grades = $enrollments->map(fn ($enrollment) => [
            'course' => [
                'title' => $enrollment->courseOffering->course->title,
                'code' => $enrollment->courseOffering->course->code,
                'credit_units' => $enrollment->courseOffering->course->credit_units,
                'semester' => $enrollment->courseOffering->semester,
            ],
            'grade' => $enrollment->grade
                                ? new GradeResource($enrollment->grade)
                                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Grades retrieved successfully.',
            'data' => [
                'session' => $session->name,
                'semester' => $semester,
                'grades' => $grades,
            ],
        ]);
    }

    /**
     * The student's whole academic record, oldest first.
     *
     * One request rather than one per semester: a transcript is a single
     * document, and building it from six separate calls to /grades would mean
     * the printed page could show a half-loaded record.
     *
     * Only approved grades count. A pending or rejected mark is not part of
     * anyone's academic history, and a transcript that included them would
     * report results the school has not actually released.
     */
    public function transcript(Request $request): JsonResponse
    {
        $student = $request->user();

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('grade', fn ($query) => $query->where('status', GradeStatus::Approved->value))
            ->with([
                'courseOffering.course',
                'courseOffering.academicSession',
                'grade',
            ])
            ->get();

        $gpaRecords = GpaRecord::where('student_id', $student->id)
            ->where('total_credit_units', '>', 0)
            ->with('academicSession')
            ->get();

        $periods = $enrollments
            ->groupBy(fn ($enrollment) => $enrollment->courseOffering->academic_session_id
                .'-'.$enrollment->courseOffering->semester->value)
            ->map(function ($group) use ($gpaRecords) {
                $offering = $group->first()->courseOffering;
                $session = $offering->academicSession;
                $semester = $offering->semester;

                $record = $gpaRecords->first(fn ($gpa) => $gpa->academic_session_id === $session->id
                    && $gpa->semester === $semester);

                return [
                    'session' => $session->name,
                    'session_start_year' => $session->start_year,
                    'semester' => $semester->value,
                    'courses' => $group
                        ->sortBy(fn ($enrollment) => $enrollment->courseOffering->course->code)
                        ->map(fn ($enrollment) => [
                            'code' => $enrollment->courseOffering->course->code,
                            'title' => $enrollment->courseOffering->course->title,
                            'credit_units' => $enrollment->courseOffering->course->credit_units,
                            'letter_grade' => $enrollment->grade->letter_grade,
                            'grade_point' => $enrollment->grade->grade_point,
                            'score' => $enrollment->grade->score,
                        ])
                        ->values(),
                    'total_credit_units' => $record->total_credit_units ?? 0,
                    'gpa' => $record?->gpa,
                    'cgpa' => $record?->cgpa,
                ];
            })
            /*
             * One composite key rather than sortBy()'s multi-comparison array
             * form: passed an array of closures that overload resolves to a
             * single callback and silently sorts by nothing, which left the
             * transcript in whatever order the enrollments happened to come
             * back in. A transcript has to read forwards through time.
             */
            ->sortBy(fn ($period) => sprintf(
                '%04d-%d',
                (int) $period['session_start_year'],
                $period['semester'] === 'first' ? 1 : 2,
            ))
            ->values();

        $latest = $gpaRecords->sortByDesc(fn ($record) => [
            $record->academicSession->start_year,
            $record->semester === Semester::Second ? 2 : 1,
        ])->first();

        return response()->json([
            'success' => true,
            'message' => 'Transcript retrieved successfully.',
            'data' => [
                'student' => [
                    'name' => $student->name,
                    'student_id' => $student->student_id,
                    'department' => $student->department?->name,
                    'faculty' => $student->department?->faculty?->name,
                    'study_type' => $student->study_type,
                    'entry_year' => $student->entry_year,
                    'level' => $this->resolveLevel($student),
                ],
                'periods' => $periods,
                'cgpa' => $latest->cgpa ?? '0.00',
                'total_credit_units' => $latest->cumulative_credit_units ?? 0,
                'generated_at' => now()->toDayDateTimeString(),
            ],
        ]);
    }

    // GPA Records

    public function gpaRecords(Request $request): JsonResponse
    {
        $student = $request->user();

        /*
         * Ordered by the period the record describes, not by created_at.
         *
         * `recomputeStudentHistory()` rewrites every record for a student in
         * one pass, so all of them share a created_at to the second. `latest()`
         * had no tiebreaker and returned them in whatever order the storage
         * engine chose, which made both the CGPA below and the dashboard's GPA
         * chart depend on row order — the demo student's transcript reported a
         * CGPA of 3.05, the figure from their *first* semester, instead of 3.46.
         *
         * Ascending, so the collection reads forwards through time like the
         * transcript does and the chart can plot it without reversing.
         */
        $records = GpaRecord::query()
            ->where('gpa_records.student_id', $student->id)
            ->join('academic_sessions', 'academic_sessions.id', '=', 'gpa_records.academic_session_id')
            ->orderBy('academic_sessions.start_year')
            ->orderByRaw("CASE gpa_records.semester WHEN 'first' THEN 1 ELSE 2 END")
            ->select('gpa_records.*')
            ->with('academicSession')
            ->get();

        /*
         * The most recent period carries the running CGPA.
         *
         * `->`, not `?->`: Larastan types last() as non-null here, so the
         * nullsafe operator trips its nullsafe.neverNull rule. It is still safe
         * on an empty collection — `??` evaluates the left side with isset()
         * semantics, so a null base yields the fallback rather than a warning.
         * This is the same form the transcript and dashboard already use.
         */
        $cgpa = $records->last()->cgpa ?? '0.00';

        return response()->json([
            'success' => true,
            'message' => 'GPA records retrieved successfully.',
            'data' => [
                'cgpa' => $cgpa,
                'records' => GpaRecordResource::collection($records),
            ],
        ]);
    }

    // Helpers

    /**
     * The student's academic level, formatted for display.
     *
     * Delegates to User::level rather than deriving the level again here.
     * The previous implementation counted from now()->year, while User::level
     * (and therefore every admin list, the level filters and the registration
     * queue) counts from the current academic session's start year. The two
     * disagreed for the whole of the first semester: a student read
     * "500 Level" on their own dashboard while every admin screen showed 400.
     */
    protected function resolveLevel(User $student): ?string
    {
        $level = $student->level;

        return $level === null ? null : "{$level} Level";
    }
}
