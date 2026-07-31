<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'duration_years' => 4,
        ];
    }
}
