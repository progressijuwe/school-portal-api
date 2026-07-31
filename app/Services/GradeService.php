<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GradeAuditAction;
use App\Enums\GradeStatus;
use App\Enums\Semester;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GradeService
{
    /**
     * Grade bands, ordered from highest to lowest.
     *
     * Only the lower bound is meaningful. The previous version carried explicit
     * min/max pairs with gaps between them (A- topped out at 94, A started at
     * 95), so a fractional score landing in a gap fell through to the F
     * fallback at the bottom of the loop.
     *
     * @var array<int, array{grade: string, min: float, points: float}>
     */
    private const GRADE_SCALE = [
        ['grade' => 'A', 'min' => 95.0, 'points' => 4.00],
        ['grade' => 'A-', 'min' => 89.0, 'points' => 3.75],
        ['grade' => 'B+', 'min' => 83.0, 'points' => 3.25],
        ['grade' => 'B', 'min' => 77.0, 'points' => 3.00],
        ['grade' => 'B-', 'min' => 71.0, 'points' => 2.75],
        ['grade' => 'C+', 'min' => 65.0, 'points' => 2.25],
        ['grade' => 'C', 'min' => 59.0, 'points' => 2.00],
        ['grade' => 'C-', 'min' => 53.0, 'points' => 1.75],
        ['grade' => 'D', 'min' => 48.0, 'points' => 1.00],
        ['grade' => 'F', 'min' => 0.0, 'points' => 0.00],
    ];

    /**
     * @return array{letter_grade: string, grade_point: float}
     */
    public function resolveGrade(float $score): array
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException("Score {$score} is outside the valid range of 0-100.");
        }

        foreach (self::GRADE_SCALE as $band) {
            if ($score >= $band['min']) {
                return [
                    'letter_grade' => $band['grade'],
                    'grade_point' => $band['points'],
                ];
            }
        }

        // Unreachable while the lowest band starts at 0 — but failing loudly
        // beats silently recording an F.
        throw new InvalidArgumentException("No grade band matches score {$score}.");
    }

    /**
     * Create or update the grade for an enrollment, and record an audit entry.
     *
     * Callers are responsible for authorization (see EnrollmentPolicy::grade)
     * and for wrapping batches in a transaction.
     *
     * @param  array{ca_score: int|null, project_score: int|null, exam_score: int|null}  $components
     */
    public function persist(
        Enrollment $enrollment,
        array $components,
        GradeStatus $status,
        User $lecturer,
        ?string $ipAddress = null,
    ): Grade {
        $existing = Grade::where('enrollment_id', $enrollment->id)->first();
        $before = $existing?->only([
            'ca_score', 'project_score', 'exam_score', 'score',
            'letter_grade', 'grade_point', 'status',
        ]);

        $total = (int) ($components['ca_score'] ?? 0)
            + (int) ($components['project_score'] ?? 0)
            + (int) ($components['exam_score'] ?? 0);

        // A draft is the lecturer's working copy — no letter grade is resolved
        // until they actually submit for approval.
        $resolved = $status === GradeStatus::Draft
            ? ['letter_grade' => null, 'grade_point' => null]
            : $this->resolveGrade((float) $total);

        $grade = Grade::updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'submitted_by' => $lecturer->id,
                'ca_score' => $components['ca_score'] ?? null,
                'project_score' => $components['project_score'] ?? null,
                'exam_score' => $components['exam_score'] ?? null,
                'score' => $total,
                'letter_grade' => $resolved['letter_grade'],
                'grade_point' => $resolved['grade_point'],
                'status' => $status,
                'rejection_reason' => null,
                'submitted_at' => $status === GradeStatus::Draft ? null : now(),
                'approved_at' => null,
                'approved_by' => null,
            ]
        );

        $this->audit(
            $grade,
            $lecturer,
            $this->actionFor($status, $before),
            $before,
            ipAddress: $ipAddress,
        );

        return $grade;
    }

    /**
     * Append an immutable audit row for a grade write.
     *
     * @param  array<string, mixed>|null  $before
     */
    public function audit(
        Grade $grade,
        ?User $actor,
        GradeAuditAction $action,
        ?array $before = null,
        ?string $reason = null,
        ?string $ipAddress = null,
    ): void {
        $grade->audits()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'before' => $before,
            'after' => $grade->only([
                'ca_score', 'project_score', 'exam_score', 'score',
                'letter_grade', 'grade_point', 'status',
            ]),
            'reason' => $reason,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function actionFor(GradeStatus $status, ?array $before): GradeAuditAction
    {
        if ($status === GradeStatus::Draft) {
            return GradeAuditAction::DraftSaved;
        }

        $wasSubmitted = $before !== null
            && in_array($before['status'] ?? null, [GradeStatus::Pending, GradeStatus::Rejected], true);

        return $wasSubmitted ? GradeAuditAction::Resubmitted : GradeAuditAction::Submitted;
    }

    /**
     * Recompute a student's entire GPA history in chronological order.
     *
     * The previous implementation recalculated a single semester and derived its
     * CGPA from the sum of every *other* stored record, without ever touching
     * those records. Approving a backdated grade for 2024/first therefore left
     * 2024/second holding stale cumulative totals — and the admin summary reads
     * the latest record, so it showed the stale one.
     *
     * Replaying the whole history is O(semesters) and removes the ordering
     * dependency entirely.
     */
    public function recomputeStudentHistory(int $studentId): void
    {
        DB::transaction(function () use ($studentId) {
            $cumulativeUnits = 0;
            $cumulativePoints = 0.0;

            foreach ($this->orderedPeriodsFor($studentId) as [$sessionId, $semester]) {
                [$units, $points] = $this->semesterTotals($studentId, $sessionId, $semester);

                $cumulativeUnits += $units;
                $cumulativePoints += $points;

                GpaRecord::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_session_id' => $sessionId,
                        'semester' => $semester,
                    ],
                    [
                        'gpa' => $units > 0 ? round($points / $units, 2) : 0.00,
                        'cgpa' => $cumulativeUnits > 0 ? round($cumulativePoints / $cumulativeUnits, 2) : 0.00,
                        'total_credit_units' => $units,
                        'total_grade_points' => $points,
                        'cumulative_credit_units' => $cumulativeUnits,
                        'cumulative_grade_points' => $cumulativePoints,
                    ]
                );
            }
        });
    }

    /**
     * Every (session, semester) the student has activity in, oldest first.
     *
     * Includes periods that already have a GPA record so that a semester whose
     * last approved grade was revoked still gets recalculated down to zero
     * rather than being silently skipped.
     *
     * @return array<int, array{0: int, 1: Semester}>
     */
    private function orderedPeriodsFor(int $studentId): array
    {
        $graded = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->select('course_offerings.academic_session_id', 'course_offerings.semester')
            ->distinct()
            // toBase(): these rows carry only two joined columns, so they are
            // plain records rather than Enrollment models.
            ->toBase()
            ->get()
            ->map(fn ($row) => [(int) $row->academic_session_id, $row->semester]);

        $existing = GpaRecord::query()
            ->where('student_id', $studentId)
            ->select('academic_session_id', 'semester')
            ->distinct()
            ->get()
            ->map(fn ($row) => [(int) $row->academic_session_id, $row->semester]);

        $sessionOrder = AcademicSession::query()
            ->orderBy('start_year')
            ->orderBy('id')
            ->pluck('id')
            ->flip()
            ->all();

        return $graded->concat($existing)
            ->map(fn (array $period) => [
                $period[0],
                $period[1] instanceof Semester ? $period[1] : Semester::from((string) $period[1]),
            ])
            ->unique(fn (array $period) => $period[0].'-'.$period[1]->value)
            ->sortBy(fn (array $period) => [
                $sessionOrder[$period[0]] ?? PHP_INT_MAX,
                $period[1]->order(),
            ])
            ->values()
            ->all();
    }

    /**
     * Credit units and weighted grade points from approved grades only.
     *
     * @return array{0: int, 1: float}
     */
    private function semesterTotals(int $studentId, int $sessionId, Semester $semester): array
    {
        $row = Enrollment::query()
            ->where('enrollments.student_id', $studentId)
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->join('courses', 'courses.id', '=', 'course_offerings.course_id')
            ->join('grades', 'grades.enrollment_id', '=', 'enrollments.id')
            ->where('course_offerings.academic_session_id', $sessionId)
            ->where('course_offerings.semester', $semester->value)
            ->where('grades.status', GradeStatus::Approved->value)
            ->selectRaw('COALESCE(SUM(courses.credit_units), 0) as units')
            ->selectRaw('COALESCE(SUM(courses.credit_units * grades.grade_point), 0) as points')
            // Aggregate aliases, not Enrollment attributes.
            ->toBase()
            ->first();

        return [(int) $row->units, (float) $row->points];
    }
}
