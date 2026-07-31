<?php

namespace Tests\Feature;

use App\Enums\Semester;
use App\Models\AcademicSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every "this semester" screen used to hardcode `first`, so from January
     * onward the portal silently showed the wrong half of the year until the
     * user changed the dropdown by hand.
     */
    public function test_it_reports_the_first_semester_while_today_is_inside_it(): void
    {
        $session = AcademicSession::factory()->current(Semester::First)->create();

        $this->assertSame(Semester::First, $session->currentSemester());
    }

    public function test_it_reports_the_second_semester_once_it_has_started(): void
    {
        $session = AcademicSession::factory()->current(Semester::Second)->create();

        $this->assertSame(Semester::Second, $session->currentSemester());
    }

    public function test_it_falls_back_to_the_first_semester_when_dates_are_not_configured(): void
    {
        $session = AcademicSession::factory()->create([
            'first_semester_start' => null,
            'first_semester_end' => null,
            'second_semester_start' => null,
            'second_semester_end' => null,
        ]);

        $this->assertSame(Semester::First, $session->currentSemester());
    }

    public function test_it_reports_the_first_semester_before_the_session_begins(): void
    {
        $session = AcademicSession::factory()->create([
            'first_semester_start' => now()->addMonths(2),
            'first_semester_end' => now()->addMonths(5),
            'second_semester_start' => now()->addMonths(6),
            'second_semester_end' => now()->addMonths(11),
        ]);

        $this->assertSame(Semester::First, $session->currentSemester());
    }
}
