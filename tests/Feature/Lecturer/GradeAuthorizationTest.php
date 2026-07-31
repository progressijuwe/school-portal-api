<?php

namespace Tests\Feature\Lecturer;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The denial paths for grading.
 *
 * These are the most important tests in the suite. Before the ownership checks
 * existed, every one of them passed with a 201 — any authenticated lecturer
 * could post an arbitrary enrollment_id and overwrite any student's grade in
 * any course in the university.
 */
class GradeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturer;

    private User $otherLecturer;

    private Enrollment $ownEnrollment;

    private Enrollment $foreignEnrollment;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->lecturer = User::factory()->lecturer()->create();
        $this->otherLecturer = User::factory()->lecturer()->create();

        $this->ownEnrollment = Enrollment::factory()->create([
            'course_offering_id' => CourseOffering::factory()
                ->taughtBy($this->lecturer)
                ->create()->id,
        ]);

        $this->foreignEnrollment = Enrollment::factory()->create([
            'course_offering_id' => CourseOffering::factory()
                ->taughtBy($this->otherLecturer)
                ->create()->id,
        ]);
    }

    public function test_a_lecturer_can_grade_their_own_students(): void
    {
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 18,
                'project_score' => 17,
                'exam_score' => 55,
            ])
            ->assertCreated()
            ->assertJsonPath('data.score', '90.00')
            ->assertJsonPath('data.letter_grade', 'A-');

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $this->ownEnrollment->id,
            'submitted_by' => $this->lecturer->id,
            'status' => GradeStatus::Pending->value,
        ]);
    }

    public function test_a_lecturer_cannot_grade_another_lecturers_student(): void
    {
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->foreignEnrollment->id,
                'ca_score' => 20,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('grades', [
            'enrollment_id' => $this->foreignEnrollment->id,
        ]);
    }

    public function test_a_lecturer_cannot_batch_submit_a_foreign_enrollment(): void
    {
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades/batch', [
                'grades' => [
                    [
                        'enrollment_id' => $this->ownEnrollment->id,
                        'ca_score' => 10,
                        'project_score' => 10,
                        'exam_score' => 30,
                    ],
                    [
                        'enrollment_id' => $this->foreignEnrollment->id,
                        'ca_score' => 20,
                        'project_score' => 20,
                        'exam_score' => 60,
                    ],
                ],
            ])
            ->assertStatus(422);

        // The whole batch is rejected, so even the legitimate row must not land.
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_a_lecturer_cannot_save_a_draft_for_a_foreign_enrollment(): void
    {
        // Drafts were previously the one grading path with no guard at all.
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades/draft', [
                'grades' => [[
                    'enrollment_id' => $this->foreignEnrollment->id,
                    'ca_score' => 15,
                ]],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_updating_a_grade_writes_to_the_route_bound_record(): void
    {
        $grade = Grade::factory()->create([
            'enrollment_id' => $this->ownEnrollment->id,
            'submitted_by' => $this->lecturer->id,
        ]);

        // The old implementation authorized $grade but then persisted whatever
        // enrollment_id the body carried — validating one row and writing another.
        $this->actingAs($this->lecturer, 'sanctum')
            ->patchJson("/api/lecturer/grades/{$grade->id}", [
                'enrollment_id' => $this->foreignEnrollment->id,
                'ca_score' => 20,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('grades', [
            'enrollment_id' => $this->foreignEnrollment->id,
        ]);
    }

    public function test_a_lecturer_cannot_update_a_grade_submitted_by_someone_else(): void
    {
        $grade = Grade::factory()->create([
            'enrollment_id' => $this->foreignEnrollment->id,
            'submitted_by' => $this->otherLecturer->id,
        ]);

        $this->actingAs($this->lecturer, 'sanctum')
            ->patchJson("/api/lecturer/grades/{$grade->id}", [
                'enrollment_id' => $this->foreignEnrollment->id,
                'ca_score' => 20,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertStatus(422);
    }

    public function test_an_approved_grade_cannot_be_changed(): void
    {
        Grade::factory()->approved()->create([
            'enrollment_id' => $this->ownEnrollment->id,
            'submitted_by' => $this->lecturer->id,
            'score' => 75,
        ]);

        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 20,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('grades', [
            'enrollment_id' => $this->ownEnrollment->id,
            'score' => 75.00,
        ]);
    }

    public function test_a_dropped_enrollment_cannot_be_graded(): void
    {
        $this->ownEnrollment->update(['status' => EnrollmentStatus::Dropped]);

        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 10,
                'project_score' => 10,
                'exam_score' => 30,
            ])
            ->assertStatus(422);
    }

    public function test_a_student_cannot_reach_the_grading_endpoints(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 20,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertForbidden();
    }

    public function test_scores_must_be_whole_numbers(): void
    {
        // The component columns are unsignedTinyInteger; accepting 17.5 here
        // silently truncated it to 17 and shifted the letter grade.
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 17.5,
                'project_score' => 17,
                'exam_score' => 55,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ca_score');
    }

    public function test_scores_cannot_exceed_their_component_maximum(): void
    {
        $this->actingAs($this->lecturer, 'sanctum')
            ->postJson('/api/lecturer/grades', [
                'enrollment_id' => $this->ownEnrollment->id,
                'ca_score' => 21,
                'project_score' => 20,
                'exam_score' => 60,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ca_score');
    }
}
