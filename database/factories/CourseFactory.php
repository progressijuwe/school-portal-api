<?php

namespace Database\Factories;

use App\Enums\CourseType;
use App\Enums\Semester;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'code' => strtoupper(fake()->unique()->lexify('???')).' '.fake()->unique()->numberBetween(100, 599),
            'credit_units' => fake()->numberBetween(1, 6),
            'level' => (string) fake()->randomElement([100, 200, 300, 400, 500]),
            'semester' => Semester::First,
            'type' => CourseType::Compulsory,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function withCreditUnits(int $units): static
    {
        return $this->state(fn () => ['credit_units' => $units]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
