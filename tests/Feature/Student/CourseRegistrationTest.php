<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\Semester;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CourseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = AcademicSession::factory()->startingIn(2024)->current()->create();
        $this->student = User::factory()->student()->create();

        config([
            'academics.min_credit_units_per_semester' => 15,
            'academics.max_credit_units_per_semester' => 24,
        ]);
    }

    public function test_a_student_can_register_within_the_credit_limits(): void
    {
        $offerings = $this->offerings([6, 6, 6]);   // 18 units

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $offerings->pluck('id')->all(),
            ])
            ->assertCreated()
            ->assertJsonCount(3, 'data');

        $this->assertDatabaseCount('enrollments', 3);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $this->student->id,
            'status' => EnrollmentStatus::Pending->value,
        ]);
    }

    public function test_registration_is_rejected_above_the_maximum_credit_load(): void
    {
        $offerings = $this->offerings([6, 6, 6, 6, 6]);   // 30 units

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $offerings->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_offering_ids');

        // Nothing partially written.
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_registration_is_rejected_below_the_minimum_credit_load(): void
    {
        $offerings = $this->offerings([3, 3]);   // 6 units

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $offerings->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_offering_ids');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_existing_registrations_count_toward_the_credit_limit(): void
    {
        // 18 units already held, all pending approval.
        $this->offerings([6, 6, 6])->each(fn ($offering) => Enrollment::factory()
            ->pending()
            ->create([
                'student_id' => $this->student->id,
                'course_offering_id' => $offering->id,
            ]));

        // Adding 18 more would take the student to 36, well past the 24 cap.
        // The check must account for what they already hold, not just the
        // incoming basket — which is exactly what the old N+1 sum got right and
        // the duplicated second check made confusing.
        $more = $this->offerings([6, 6, 6]);

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $more->pluck('id')->all(),
            ])
            ->assertStatus(422);
    }

    public function test_a_student_cannot_register_twice_for_the_same_offering(): void
    {
        $offerings = $this->offerings([6, 6, 6]);

        Enrollment::factory()->pending()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offerings->first()->id,
        ]);

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $offerings->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_offering_ids');
    }

    public function test_the_same_offering_cannot_appear_twice_in_one_submission(): void
    {
        $offering = $this->offerings([6])->first();

        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => [$offering->id, $offering->id],
            ])
            ->assertStatus(422);
    }

    public function test_a_registration_cannot_straddle_two_semesters(): void
    {
        $first = $this->offerings([6, 6], Semester::First);
        $second = $this->offerings([6], Semester::Second);

        // The credit limit is a per-semester rule, so a mixed basket cannot be
        // validated coherently.
        $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $first->pluck('id')->concat($second->pluck('id'))->all(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_offering_ids');
    }

    public function test_a_lecturer_cannot_register_for_courses(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->actingAs($lecturer, 'sanctum')
            ->postJson('/api/student/enrollments', [
                'course_offering_ids' => $this->offerings([6, 6, 6])->pluck('id')->all(),
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<int, int>  $creditUnits
     * @return Collection<int, CourseOffering>
     */
    private function offerings(array $creditUnits, Semester $semester = Semester::First)
    {
        return collect($creditUnits)->map(fn (int $units) => CourseOffering::factory()
            ->inPeriod($this->session, $semester)
            ->create([
                'course_id' => Course::factory()
                    ->withCreditUnits($units)
                    ->create(['department_id' => $this->student->department_id])
                    ->id,
            ]));
    }
}
