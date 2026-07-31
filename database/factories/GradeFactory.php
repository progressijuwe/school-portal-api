<?php

namespace Database\Factories;

use App\Enums\GradeStatus;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ca = fake()->numberBetween(0, 20);
        $project = fake()->numberBetween(0, 20);
        $exam = fake()->numberBetween(0, 60);

        return [
            'enrollment_id' => Enrollment::factory(),
            'submitted_by' => User::factory()->lecturer(),
            'ca_score' => $ca,
            'project_score' => $project,
            'exam_score' => $exam,
            'score' => $ca + $project + $exam,
            'letter_grade' => 'C',
            'grade_point' => 2.00,
            'status' => GradeStatus::Pending,
            'submitted_at' => now(),
        ];
    }

    /**
     * A grade with an exact total, so a test can assert on the resulting GPA
     * rather than on whatever the random components happened to produce.
     */
    public function scoring(int $total, float $gradePoint, string $letter): static
    {
        return $this->state(fn () => [
            'ca_score' => min($total, 20),
            'project_score' => max(0, min($total - 20, 20)),
            'exam_score' => max(0, $total - 40),
            'score' => $total,
            'letter_grade' => $letter,
            'grade_point' => $gradePoint,
        ]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status' => GradeStatus::Approved,
            'approved_by' => $approver->id ?? User::factory()->admin()->create()->id,
            'approved_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => GradeStatus::Draft,
            'letter_grade' => null,
            'grade_point' => null,
            'submitted_at' => null,
        ]);
    }

    public function rejected(string $reason = 'Scores do not match the mark sheet.'): static
    {
        return $this->state(fn () => [
            'status' => GradeStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
