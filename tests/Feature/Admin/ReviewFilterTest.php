<?php

namespace Tests\Feature\Admin;

use App\Enums\Semester;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Query-parameter coverage for the two admin review queues.
 *
 * The registration queue builds its query from Enrollment and joins users, so
 * User's `atLevel` scope was not callable on it — every request with a `level`
 * filter returned a 500. It shipped because the review tests only ever hit the
 * unfiltered endpoint. Each filter now has a test that would have caught it.
 */
class ReviewFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->session = AcademicSession::factory()->current(Semester::First)->create();
    }

    /* -------------------------------------------------------- registrations */

    public function test_registrations_can_be_filtered_by_level(): void
    {
        // A student who entered this session's start year is at 100 level;
        // three years earlier puts them at 400.
        $freshman = $this->studentWithPendingRegistration($this->session->start_year);
        $finalYear = $this->studentWithPendingRegistration($this->session->start_year - 3);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&level=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $freshman->name)
            ->assertJsonPath('data.0.level', '100');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&level=400')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $finalYear->name);
    }

    public function test_the_top_level_filter_catches_everyone_who_entered_earlier(): void
    {
        // 500 level is a catch-all: four years back *or more*.
        $this->studentWithPendingRegistration($this->session->start_year - 4);
        $this->studentWithPendingRegistration($this->session->start_year - 7);
        $this->studentWithPendingRegistration($this->session->start_year);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&level=500')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_registrations_can_be_filtered_by_department(): void
    {
        $wanted = $this->studentWithPendingRegistration();
        $this->studentWithPendingRegistration();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&department_id='.$wanted->department_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $wanted->name);
    }

    public function test_registrations_can_be_filtered_by_faculty(): void
    {
        $faculty = Faculty::factory()->create();
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);

        $wanted = $this->studentWithPendingRegistration(department: $department);
        $this->studentWithPendingRegistration();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&faculty_id='.$faculty->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $wanted->name);
    }

    public function test_registrations_can_be_searched_by_name_and_matric_number(): void
    {
        $wanted = $this->studentWithPendingRegistration();
        $wanted->update(['name' => 'Chiamaka Adeyemi', 'student_id' => 'SOF/25U/900001']);
        $this->studentWithPendingRegistration();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&search=Chiamaka')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/registrations?status=pending&search=900001')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_every_registration_filter_combination_returns_a_successful_response(): void
    {
        $this->studentWithPendingRegistration();

        // A smoke pass over each parameter — the original failure was a 500,
        // not a wrong result, so simply exercising each one has value.
        $filters = [
            'level=100', 'level=200', 'level=300', 'level=400', 'level=500',
            'department_id=1', 'faculty_id=1', 'search=test',
            'level=300&department_id=1', 'search=a&level=100&faculty_id=1',
        ];

        foreach ($filters as $filter) {
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/registrations?status=pending&{$filter}")
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    /* -------------------------------------------------------------- results */

    public function test_every_result_filter_combination_returns_a_successful_response(): void
    {
        $this->offeringWithPendingGrade();

        $filters = [
            'level=100', 'level=400',
            'department_id=1', 'faculty_id=1', 'search=SEN',
            'level=400&department_id=1', 'search=a&faculty_id=1',
        ];

        foreach ($filters as $filter) {
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/admin/results?status=pending&{$filter}")
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_results_can_be_searched_by_course_code(): void
    {
        $offering = $this->offeringWithPendingGrade();
        $this->offeringWithPendingGrade();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/results?status=pending&search='.$offering->course->code)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $offering->course->code);
    }

    /* ------------------------------------------------------------- helpers */

    private function studentWithPendingRegistration(?int $entryYear = null, ?Department $department = null): User
    {
        $student = User::factory()
            ->student($entryYear ?? $this->session->start_year)
            ->create($department ? ['department_id' => $department->id] : []);

        Enrollment::factory()->pending()->create([
            'student_id' => $student->id,
            'course_offering_id' => CourseOffering::factory()
                ->inPeriod($this->session)
                ->create()->id,
        ]);

        return $student;
    }

    private function offeringWithPendingGrade(): CourseOffering
    {
        $offering = CourseOffering::factory()
            ->inPeriod($this->session)
            ->create(['course_id' => Course::factory()->create()->id]);

        Grade::factory()->create([
            'enrollment_id' => Enrollment::factory()->create([
                'course_offering_id' => $offering->id,
            ])->id,
        ]);

        return $offering;
    }
}
