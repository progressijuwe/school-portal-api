<?php

namespace Database\Seeders;

use App\Enums\GradeStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\GradeService;
use Illuminate\Database\Seeder;

/**
 * A demo dataset the application's own rules agree with.
 *
 * Two things were wrong before.
 *
 * The GPA history was written straight into `gpa_records` with no grades behind
 * it. `GradeService::recomputeStudentHistory()` rebuilds those rows from
 * approved grades and runs whenever an admin approves anything — so the first
 * approval in a demo wiped the invented history and dropped the student's CGPA
 * from 3.77 to whatever the single real grade said. Here the grades come first
 * and the GPA records are computed from them by the same service the
 * application uses, so the numbers survive any recompute.
 *
 * And the catalogue offered nine credit units against a fifteen-unit minimum,
 * which made course registration impossible to complete on a fresh install: the
 * API rejected every submission as below the minimum. The current semester now
 * carries enough choice to build a valid load.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Courses the demo student has already passed, per session and semester.
     *
     * Scores are spread across the grade bands on purpose — a transcript where
     * everything is an A tells you nothing about whether the grading maths
     * works.
     *
     * @var array<string, array<string, array<int, array{code: string, title: string, units: int, level: string, score: int}>>>
     */
    private const HISTORY = [
        '2023/2024' => [
            'first' => [
                ['code' => 'SEN 101', 'title' => 'Introduction to Software Engineering', 'units' => 3, 'level' => '100', 'score' => 84],
                ['code' => 'CSC 101', 'title' => 'Introduction to Computer Science', 'units' => 3, 'level' => '100', 'score' => 86],
                ['code' => 'MTH 101', 'title' => 'Elementary Mathematics I', 'units' => 3, 'level' => '100', 'score' => 79],
                ['code' => 'GST 101', 'title' => 'Communication in English', 'units' => 2, 'level' => '100', 'score' => 90],
                ['code' => 'PHY 101', 'title' => 'General Physics I', 'units' => 3, 'level' => '100', 'score' => 66],
            ],
            'second' => [
                ['code' => 'SEN 102', 'title' => 'Software Engineering Practice', 'units' => 3, 'level' => '100', 'score' => 89],
                ['code' => 'CSC 102', 'title' => 'Programming Fundamentals', 'units' => 3, 'level' => '100', 'score' => 91],
                ['code' => 'MTH 102', 'title' => 'Elementary Mathematics II', 'units' => 3, 'level' => '100', 'score' => 83],
                ['code' => 'GST 102', 'title' => 'Nigerian Peoples and Culture', 'units' => 2, 'level' => '100', 'score' => 80],
                ['code' => 'PHY 102', 'title' => 'General Physics II', 'units' => 3, 'level' => '100', 'score' => 78],
            ],
        ],
        '2024/2025' => [
            'first' => [
                ['code' => 'SEN 201', 'title' => 'Data Structures and Algorithms', 'units' => 3, 'level' => '200', 'score' => 92],
                ['code' => 'SEN 203', 'title' => 'Object Oriented Programming', 'units' => 3, 'level' => '200', 'score' => 90],
                ['code' => 'CSC 201', 'title' => 'Computer Architecture', 'units' => 3, 'level' => '200', 'score' => 87],
                ['code' => 'MTH 201', 'title' => 'Linear Algebra', 'units' => 3, 'level' => '200', 'score' => 84],
                ['code' => 'GST 201', 'title' => 'Logic and Philosophy', 'units' => 2, 'level' => '200', 'score' => 93],
            ],
            'second' => [
                ['code' => 'SEN 202', 'title' => 'Software Requirements Engineering', 'units' => 3, 'level' => '200', 'score' => 96],
                ['code' => 'SEN 204', 'title' => 'Database Systems', 'units' => 3, 'level' => '200', 'score' => 91],
                ['code' => 'CSC 202', 'title' => 'Operating Systems', 'units' => 3, 'level' => '200', 'score' => 85],
                ['code' => 'CSC 204', 'title' => 'Computer Networks', 'units' => 3, 'level' => '200', 'score' => 89],
                ['code' => 'GST 202', 'title' => 'Peace and Conflict Resolution', 'units' => 2, 'level' => '200', 'score' => 92],
            ],
        ],
        '2025/2026' => [
            'first' => [
                ['code' => 'SEN 301', 'title' => 'Software Architecture and Design', 'units' => 3, 'level' => '300', 'score' => 95],
                ['code' => 'SEN 303', 'title' => 'Web Application Development', 'units' => 3, 'level' => '300', 'score' => 97],
                ['code' => 'SEN 305', 'title' => 'Software Quality Assurance', 'units' => 3, 'level' => '300', 'score' => 88],
                ['code' => 'CSC 301', 'title' => 'Algorithms and Complexity', 'units' => 3, 'level' => '300', 'score' => 86],
                ['code' => 'SEN 307', 'title' => 'Human Factors in Software', 'units' => 2, 'level' => '300', 'score' => 94],
            ],
        ],
    ];

    /**
     * What the current semester offers.
     *
     * Twenty credit units against a fifteen-unit minimum and a twenty-four-unit
     * maximum, so a student has a real choice to make rather than being forced
     * to tick every box to reach the floor.
     *
     * @var array<int, array{code: string, title: string, units: int, level: string, day: string|null, start: string|null, end: string|null, venue: string|null}>
     */
    private const CURRENT_SEMESTER = [
        ['code' => 'SEN 402', 'title' => 'Software Construction', 'units' => 3, 'level' => '400', 'day' => 'monday', 'start' => '09:00:00', 'end' => '11:00:00', 'venue' => 'LAB1'],
        ['code' => 'SEN 404', 'title' => 'Object Oriented Analysis and Design', 'units' => 3, 'level' => '400', 'day' => 'tuesday', 'start' => '09:00:00', 'end' => '11:00:00', 'venue' => 'LT1'],
        ['code' => 'SEN 406', 'title' => 'Human Computer Interaction', 'units' => 3, 'level' => '400', 'day' => 'wednesday', 'start' => '11:00:00', 'end' => '13:00:00', 'venue' => 'LT1'],
        ['code' => 'SEN 408', 'title' => 'Software Testing and Verification', 'units' => 3, 'level' => '400', 'day' => 'thursday', 'start' => '09:00:00', 'end' => '11:00:00', 'venue' => 'LAB1'],
        ['code' => 'SEN 410', 'title' => 'Distributed Systems', 'units' => 3, 'level' => '400', 'day' => 'friday', 'start' => '13:00:00', 'end' => '15:00:00', 'venue' => 'LT1'],
        ['code' => 'SEN 412', 'title' => 'Software Project Management', 'units' => 3, 'level' => '400', 'day' => null, 'start' => null, 'end' => null, 'venue' => null],
        ['code' => 'GST 402', 'title' => 'Entrepreneurship and Innovation', 'units' => 2, 'level' => '400', 'day' => null, 'start' => null, 'end' => null, 'venue' => null],
    ];

    /**
     * The load the demo student is already registered for — a valid eighteen
     * units, leaving two courses unregistered so their own available-offerings
     * list is not empty either.
     *
     * @var array<int, string>
     */
    private const ENROLLED_NOW = ['SEN 402', 'SEN 404', 'SEN 406', 'SEN 408', 'SEN 410', 'SEN 412'];

    public function run(): void
    {
        $department = Department::where('code', 'SOF')->firstOrFail();
        $currentSession = AcademicSession::where('is_current', true)->firstOrFail();

        $student = User::where('email', 'student1@aust.edu.ng')->firstOrFail();
        $lecturer = User::where('email', 'lecturer1@aust.edu.ng')->firstOrFail();
        $secondLecturer = User::where('email', 'lecturer2@aust.edu.ng')->first() ?? $lecturer;
        $admin = User::role('admin')->firstOrFail();

        $venues = $this->seedVenues();

        $this->seedHistory($department, $student, $lecturer, $admin);
        $this->seedCurrentSemester(
            $department,
            $currentSession,
            $student,
            $lecturer,
            $secondLecturer,
            $venues,
        );

        // GPA records are derived, never written by hand. This is the same call
        // the queued job makes after an approval, so running it again later
        // reproduces exactly these numbers instead of contradicting them.
        app(GradeService::class)->recomputeStudentHistory($student->id);

        $units = collect(self::CURRENT_SEMESTER)->sum('units');

        $this->command->info(
            "Demo data seeded: {$units} credit units offered this semester, "
            .'GPA history computed from approved grades.'
        );
    }

    /**
     * @return array<string, Venue>
     */
    private function seedVenues(): array
    {
        $venues = [
            'LT1' => ['name' => 'Lecture Theatre 1', 'building' => 'NMI Building', 'type' => 'lecture_hall', 'capacity' => 150],
            'LAB1' => ['name' => 'Software Lab 1', 'building' => 'Faculty of Computing', 'type' => 'laboratory', 'capacity' => 45],
        ];

        $created = [];

        foreach ($venues as $code => $data) {
            $created[$code] = Venue::firstOrCreate(
                ['code' => $code],
                [...$data, 'is_active' => true],
            );
        }

        return $created;
    }

    /**
     * Past semesters: real enrollments carrying real approved grades.
     */
    private function seedHistory(
        Department $department,
        User $student,
        User $lecturer,
        User $admin,
    ): void {
        $gradeService = app(GradeService::class);

        foreach (self::HISTORY as $sessionName => $semesters) {
            $session = AcademicSession::where('name', $sessionName)->first();

            if ($session === null) {
                continue;
            }

            foreach ($semesters as $semester => $courses) {
                foreach ($courses as $data) {
                    $offering = $this->offeringFor($department, $session, $semester, $data, $lecturer);

                    $enrollment = Enrollment::firstOrCreate(
                        ['student_id' => $student->id, 'course_offering_id' => $offering->id],
                        ['status' => 'active'],
                    );

                    // Split into components so a mark sheet shows a plausible
                    // breakdown rather than a bare total, while still summing to
                    // the score the grade band is resolved from.
                    $exam = (int) round($data['score'] * 0.6);
                    $ca = (int) round(($data['score'] - $exam) / 2);
                    $project = $data['score'] - $exam - $ca;

                    $resolved = $gradeService->resolveGrade((float) $data['score']);
                    $awardedAt = $session->second_semester_end ?? now();

                    Grade::updateOrCreate(
                        ['enrollment_id' => $enrollment->id],
                        [
                            'submitted_by' => $lecturer->id,
                            'ca_score' => $ca,
                            'project_score' => $project,
                            'exam_score' => $exam,
                            'score' => $data['score'],
                            'letter_grade' => $resolved['letter_grade'],
                            'grade_point' => $resolved['grade_point'],
                            'status' => GradeStatus::Approved,
                            'rejection_reason' => null,
                            'submitted_at' => $awardedAt,
                            'approved_at' => $awardedAt,
                            'approved_by' => $admin->id,
                        ],
                    );
                }
            }
        }
    }

    /**
     * The semester being administered right now.
     *
     * @param  array<string, Venue>  $venues
     */
    private function seedCurrentSemester(
        Department $department,
        AcademicSession $session,
        User $student,
        User $lecturer,
        User $secondLecturer,
        array $venues,
    ): void {
        // Whichever half of the year the session's own dates put us in, so the
        // offerings land in the semester the portal opens on.
        $semester = $session->currentSemester()->value;

        foreach (self::CURRENT_SEMESTER as $index => $data) {
            $offering = $this->offeringFor(
                $department,
                $session,
                $semester,
                $data,
                // Split across both lecturers so one dashboard is not full and
                // the other empty.
                $index % 2 === 0 ? $lecturer : $secondLecturer,
            );

            if (in_array($data['code'], self::ENROLLED_NOW, true)) {
                Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'course_offering_id' => $offering->id],
                    ['status' => 'active'],
                );
            }

            if ($data['day'] !== null) {
                TimetableSlot::firstOrCreate(
                    ['course_offering_id' => $offering->id, 'day' => $data['day']],
                    [
                        'start_time' => $data['start'],
                        'end_time' => $data['end'],
                        'venue_id' => $venues[$data['venue']]->id,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * Find or create the course, and the offering that runs it.
     *
     * The offering lookup includes the semester because the unique index does.
     * Keying on course and session alone would match a different semester's
     * offering of the same course and quietly skip creating the right one.
     *
     * @param  array{code: string, title: string, units: int, level: string}  $data
     */
    private function offeringFor(
        Department $department,
        AcademicSession $session,
        string $semester,
        array $data,
        User $lecturer,
    ): CourseOffering {
        $course = Course::firstOrCreate(
            ['code' => $data['code']],
            [
                'title' => $data['title'],
                'credit_units' => $data['units'],
                'level' => $data['level'],
                'semester' => $semester,
                'type' => str_starts_with($data['code'], 'GST') ? 'elective' : 'compulsory',
                'description' => "{$data['title']}.",
                'is_active' => true,
                'department_id' => $department->id,
            ],
        );

        return CourseOffering::firstOrCreate(
            [
                'course_id' => $course->id,
                'academic_session_id' => $session->id,
                'semester' => $semester,
            ],
            ['is_active' => true, 'lecturer_id' => $lecturer->id],
        );
    }
}
