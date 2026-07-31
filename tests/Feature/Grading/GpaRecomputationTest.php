<?php

namespace Tests\Feature\Grading;

use App\Enums\Semester;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\Grade;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpaRecomputationTest extends TestCase
{
    use RefreshDatabase;

    private GradeService $service;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GradeService::class);
        $this->student = User::factory()->student()->create();
    }

    public function test_it_computes_gpa_from_approved_grades_only(): void
    {
        $session = AcademicSession::factory()->startingIn(2024)->current()->create();

        // 3 units at 4.00 and 3 units at 2.00 => 18 points over 6 units => 3.00
        $this->approvedGrade($session, Semester::First, units: 3, points: 4.00);
        $this->approvedGrade($session, Semester::First, units: 3, points: 2.00);

        // A pending grade must not count toward the GPA.
        $pending = $this->enrollment($session, Semester::First, units: 3);
        Grade::factory()->create([
            'enrollment_id' => $pending->id,
            'grade_point' => 4.00,
        ]);

        $this->service->recomputeStudentHistory($this->student->id);

        $record = GpaRecord::where('student_id', $this->student->id)->firstOrFail();

        $this->assertSame('3.00', $record->gpa);
        $this->assertSame(6, $record->total_credit_units);
    }

    /**
     * The bug this whole rewrite exists for.
     *
     * The old implementation recalculated one semester and derived its CGPA
     * from the sum of every *other* stored record, without ever touching them.
     * Approving a backdated grade for an earlier semester therefore left every
     * later record holding stale cumulative totals — and the admin summary
     * reads the latest record, so it displayed the stale one.
     */
    public function test_approving_an_earlier_semester_late_still_corrects_every_later_cgpa(): void
    {
        $first = AcademicSession::factory()->startingIn(2023)->create();
        $second = AcademicSession::factory()->startingIn(2024)->current()->create();

        // Second year is graded and computed first.
        $this->approvedGrade($second, Semester::First, units: 4, points: 2.00);
        $this->service->recomputeStudentHistory($this->student->id);

        $this->assertSame(
            '2.00',
            $this->recordFor($second, Semester::First)->cgpa,
            'Baseline: with only one semester, CGPA equals GPA.'
        );

        // Now a first-year result is approved out of order.
        $this->approvedGrade($first, Semester::First, units: 4, points: 4.00);
        $this->service->recomputeStudentHistory($this->student->id);

        // Earlier semester: 16 points over 4 units.
        $this->assertSame('4.00', $this->recordFor($first, Semester::First)->gpa);
        $this->assertSame('4.00', $this->recordFor($first, Semester::First)->cgpa);

        // Later semester's GPA is unchanged, but its CGPA now spans both:
        // (16 + 8) points over (4 + 4) units => 3.00
        $later = $this->recordFor($second, Semester::First);
        $this->assertSame('2.00', $later->gpa);
        $this->assertSame('3.00', $later->cgpa);
        $this->assertSame(8, $later->cumulative_credit_units);
    }

    public function test_cgpa_accumulates_across_semesters_in_chronological_order(): void
    {
        $session = AcademicSession::factory()->startingIn(2024)->current()->create();

        $this->approvedGrade($session, Semester::First, units: 2, points: 4.00);
        $this->approvedGrade($session, Semester::Second, units: 2, points: 1.00);

        $this->service->recomputeStudentHistory($this->student->id);

        $this->assertSame('4.00', $this->recordFor($session, Semester::First)->cgpa);

        // Second semester runs after first within the same session:
        // (8 + 2) points over 4 units => 2.50
        $this->assertSame('2.50', $this->recordFor($session, Semester::Second)->cgpa);
    }

    public function test_a_semester_with_no_approved_grades_reports_zero_rather_than_being_skipped(): void
    {
        $session = AcademicSession::factory()->startingIn(2024)->current()->create();

        $enrollment = $this->enrollment($session, Semester::First, units: 3);
        Grade::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->service->recomputeStudentHistory($this->student->id);

        $record = $this->recordFor($session, Semester::First);

        $this->assertSame('0.00', $record->gpa);
        $this->assertSame(0, $record->total_credit_units);
    }

    /* ---------------------------------------------------------------------- */

    private function enrollment(AcademicSession $session, Semester $semester, int $units): Enrollment
    {
        $offering = CourseOffering::factory()
            ->inPeriod($session, $semester)
            ->create([
                'course_id' => Course::factory()->withCreditUnits($units)->create()->id,
            ]);

        return Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
        ]);
    }

    private function approvedGrade(
        AcademicSession $session,
        Semester $semester,
        int $units,
        float $points,
    ): Grade {
        return Grade::factory()->approved()->create([
            'enrollment_id' => $this->enrollment($session, $semester, $units)->id,
            'grade_point' => $points,
        ]);
    }

    private function recordFor(AcademicSession $session, Semester $semester): GpaRecord
    {
        return GpaRecord::where('student_id', $this->student->id)
            ->where('academic_session_id', $session->id)
            ->where('semester', $semester->value)
            ->firstOrFail();
    }
}
