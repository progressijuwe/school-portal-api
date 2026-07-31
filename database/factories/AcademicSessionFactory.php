<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2015, 2035);

        return [
            'name' => $startYear.'/'.($startYear + 1),
            'start_year' => $startYear,
            'end_year' => $startYear + 1,
            'first_semester_start' => "{$startYear}-09-01",
            'first_semester_end' => "{$startYear}-12-20",
            'second_semester_start' => ($startYear + 1).'-01-15',
            'second_semester_end' => ($startYear + 1).'-06-30',
            'is_current' => false,
        ];
    }

    /**
     * The session the portal is currently in.
     *
     * Deliberately dates the semesters around *today* rather than only flipping
     * the flag. `AcademicSession::currentSemester()` reads those dates, so a
     * session flagged current but dated years in the past would report the
     * second semester as active and every "this semester" screen would come
     * back empty.
     *
     * Defaults to today sitting inside the first semester; use
     * `current(Semester::Second)` for the other half of the year.
     */
    public function current(Semester $activeSemester = Semester::First): static
    {
        return $this->state(function () use ($activeSemester) {
            $today = now()->startOfDay();

            return $activeSemester === Semester::First
                ? [
                    'is_current' => true,
                    'first_semester_start' => $today->copy()->subMonth(),
                    'first_semester_end' => $today->copy()->addMonths(2),
                    'second_semester_start' => $today->copy()->addMonths(3),
                    'second_semester_end' => $today->copy()->addMonths(8),
                ]
                : [
                    'is_current' => true,
                    'first_semester_start' => $today->copy()->subMonths(8),
                    'first_semester_end' => $today->copy()->subMonths(3),
                    'second_semester_start' => $today->copy()->subMonth(),
                    'second_semester_end' => $today->copy()->addMonths(2),
                ];
        });
    }

    /**
     * Pins the session to a specific start year, so tests that assert on
     * derived academic level are not at the mercy of a random year.
     */
    public function startingIn(int $year): static
    {
        return $this->state(fn () => [
            'name' => $year.'/'.($year + 1),
            'start_year' => $year,
            'end_year' => $year + 1,
            'first_semester_start' => "{$year}-09-01",
            'first_semester_end' => "{$year}-12-20",
            'second_semester_start' => ($year + 1).'-01-15',
            'second_semester_end' => ($year + 1).'-06-30',
        ]);
    }
}
