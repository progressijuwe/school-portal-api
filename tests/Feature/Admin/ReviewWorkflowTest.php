<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeAuditAction;
use App\Enums\GradeStatus;
use App\Enums\Semester;
use App\Jobs\RecomputeStudentGpa;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The two approval workflows the whole system exists for — and which had no
 * frontend wiring at all before this change.
 */
class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Bus::fake();

        $this->admin = User::factory()->admin()->create();
        $this->session = AcademicSession::factory()->startingIn(2024)->current()->create();
    }

    /* -- Registrations ----------------------------------------------------- */

    public function test_registrations_are_grouped_by_student(): void
    {
        $student = User::factory()->student()->create();

        collect(range(1, 3))->each(fn () => Enrollment::factory()->pending()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering()->id,
        ]));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending');

        $response->assertOk()
            // One row per student, not one per enrollment.
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $student->name)
            ->assertJsonCount(3, 'data.0.courses')
            ->assertJsonCount(3, 'data.0.enrollment_ids')
            ->assertJsonPath('meta.counts.pending', 1);
    }

    public function test_an_admin_can_approve_a_whole_registration_at_once(): void
    {
        $student = User::factory()->student()->create();

        $enrollments = collect(range(1, 3))->map(fn () => Enrollment::factory()->pending()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering()->id,
        ]));

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/registrations/bulk-review', [
                'enrollment_ids' => $enrollments->pluck('id')->all(),
                'action' => 'approve',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $enrollments->each(fn (Enrollment $enrollment) => $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Active->value,
        ]));
    }

    public function test_only_pending_registrations_can_be_reviewed(): void
    {
        $enrollment = Enrollment::factory()->create([
            'course_offering_id' => $this->offering()->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/registrations/bulk-review', [
                'enrollment_ids' => [$enrollment->id],
                'action' => 'approve',
            ])
            ->assertStatus(409);
    }

    public function test_a_lecturer_cannot_review_registrations(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $enrollment = Enrollment::factory()->pending()->create([
            'course_offering_id' => $this->offering()->id,
        ]);

        $this->actingAs($lecturer, 'sanctum')
            ->patchJson('/api/admin/registrations/bulk-review', [
                'enrollment_ids' => [$enrollment->id],
                'action' => 'approve',
            ])
            ->assertForbidden();
    }

    /* -- Results ----------------------------------------------------------- */

    public function test_results_are_grouped_by_course_offering(): void
    {
        $offering = $this->offering();

        collect(range(1, 4))->each(fn () => Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $offering->id,
            ])->id,
            'score' => 70,
        ]));

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/results?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.students', 4)
            // A whole-number average serialises without a decimal part; the
            // frontend formats it with toFixed(1) for display.
            ->assertJsonPath('data.0.avgScore', 70)
            ->assertJsonCount(4, 'data.0.grade_ids');
    }

    public function test_approving_a_mark_sheet_dispatches_one_recompute_per_student(): void
    {
        $offering = $this->offering();

        $grades = collect(range(1, 3))->map(fn () => Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $offering->id,
            ])->id,
        ]));

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/results/bulk-review', [
                'grade_ids' => $grades->pluck('id')->all(),
                'action' => 'approve',
            ])
            ->assertOk();

        $grades->each(fn (Grade $grade) => $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'status' => GradeStatus::Approved->value,
            'approved_by' => $this->admin->id,
        ]));

        // Three different students => three jobs, not one per grade per student.
        Bus::assertDispatchedTimes(RecomputeStudentGpa::class, 3);
    }

    public function test_rejecting_results_requires_a_reason(): void
    {
        $grade = Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $this->offering()->id,
            ])->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/results/bulk-review', [
                'grade_ids' => [$grade->id],
                'action' => 'reject',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_rejecting_results_records_the_reason_and_leaves_gpa_alone(): void
    {
        $grade = Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $this->offering()->id,
            ])->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/results/bulk-review', [
                'grade_ids' => [$grade->id],
                'action' => 'reject',
                'rejection_reason' => 'Exam scores do not match the mark sheet.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'status' => GradeStatus::Rejected->value,
            'rejection_reason' => 'Exam scores do not match the mark sheet.',
            'approved_by' => null,
        ]);

        Bus::assertNotDispatched(RecomputeStudentGpa::class);
    }

    public function test_every_review_decision_is_written_to_the_audit_trail(): void
    {
        $grade = Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $this->offering()->id,
            ])->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/results/bulk-review', [
                'grade_ids' => [$grade->id],
                'action' => 'approve',
            ])
            ->assertOk();

        $this->assertDatabaseHas('grade_audits', [
            'grade_id' => $grade->id,
            'actor_id' => $this->admin->id,
            'action' => GradeAuditAction::Approved->value,
        ]);
    }

    public function test_an_already_approved_grade_cannot_be_reviewed_again(): void
    {
        $grade = Grade::factory()->approved()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $this->offering()->id,
            ])->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/results/bulk-review', [
                'grade_ids' => [$grade->id],
                'action' => 'approve',
            ])
            ->assertStatus(409);
    }

    /* -- Dashboard --------------------------------------------------------- */

    public function test_the_dashboard_reports_both_approval_queues(): void
    {
        Enrollment::factory()->pending()->create([
            'course_offering_id' => $this->offering()->id,
        ]);

        Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $this->offering()->id,
            ])->id,
        ]);

        // Both of these endpoints were called by the frontend but never existed.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_registrations', 1)
            ->assertJsonPath('data.pending_grades', 1)
            ->assertJsonStructure(['data' => [
                'total_students', 'total_lecturers', 'total_courses',
                'pending_registrations', 'pending_grades',
            ]]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'label', 'time']]]);
    }

    public function test_a_student_cannot_reach_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->student()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    private function offering(Semester $semester = Semester::First): CourseOffering
    {
        return CourseOffering::factory()
            ->inPeriod($this->session, $semester)
            ->create(['course_id' => Course::factory()->create()->id]);
    }
}
