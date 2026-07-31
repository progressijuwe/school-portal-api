<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'course_offering_id' => CourseOffering::factory(),
            'status' => EnrollmentStatus::Active,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Pending]);
    }

    public function dropped(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Dropped]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => EnrollmentStatus::Rejected]);
    }
}
