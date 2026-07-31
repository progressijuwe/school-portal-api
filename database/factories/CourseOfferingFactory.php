<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    protected $model = CourseOffering::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'lecturer_id' => User::factory()->lecturer(),
            'semester' => Semester::First,
            'is_active' => true,
        ];
    }

    public function taughtBy(User $lecturer): static
    {
        return $this->state(fn () => ['lecturer_id' => $lecturer->id]);
    }

    /**
     * Not named `for()` — that is Factory's own relationship helper.
     */
    public function inPeriod(AcademicSession $session, Semester $semester = Semester::First): static
    {
        return $this->state(fn () => [
            'academic_session_id' => $session->id,
            'semester' => $semester,
        ]);
    }
}
