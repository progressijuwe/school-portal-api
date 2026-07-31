<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Enums\DayOfWeek;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\Semester;
use App\Enums\VenueType;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Volume + variety for local development.
 *
 * Every seeded record exists to put some screen into a state you cannot reach
 * with a handful of rows: enough students to paginate, enough pending
 * registrations and results to fill both approval queues across multiple pages,
 * grades in all four statuses, and a real multi-semester GPA history.
 *
 * Not run by DatabaseSeeder — this is development data, and seeding it into
 * production would create dozens of fake accounts. Run it explicitly:
 *
 *     php artisan db:seed --class=DevDataSeeder
 *
 * Safe to re-run: everything keys off deterministic emails and course codes.
 */
class DevDataSeeder extends Seeder
{
    private const PASSWORD = 'Password@123';

    /** Departments the demo data is spread across. */
    private const DEPARTMENT_CODES = ['SOF', 'CSC', 'CYB', 'MDS', 'MLS'];

    /**
     * Roughly a full class list per department.
     *
     * 20 is deliberate, not arbitrary: entry year cycles every 5 students
     * (levels 100–500) and enrollment status every 4, so the combinations only
     * close over lcm(4, 5) = 20. At 14 there was no level-200 student with a
     * pending registration, which made that filter combination untestable.
     */
    private const STUDENTS_PER_DEPARTMENT = 20;

    private const LECTURERS_PER_DEPARTMENT = 3;

    /*
     * Name pools.
     *
     * Students and lecturers draw from separate pools so their generated email
     * addresses can never collide. Within a pool, uniqueName() walks distinct
     * first/last pairs rather than cycling both on the same index — the earlier
     * version repeated after 14 and regenerated identical names in every
     * department, giving 82 students only 16 distinct names.
     */

    /** @var array<int, string> */
    private const STUDENT_FIRST_NAMES = [
        'Chiamaka', 'Tunde', 'Zainab', 'Obinna', 'Aisha', 'Segun', 'Ngozi',
        'Yusuf', 'Blessing', 'Kelechi', 'Amina', 'Femi', 'Ifeoma', 'Musa',
    ];

    /** @var array<int, string> */
    private const STUDENT_LAST_NAMES = [
        'Adeyemi', 'Okonkwo', 'Bello', 'Eze', 'Abubakar', 'Ogundele',
        'Nwachukwu', 'Sani', 'Oluwole', 'Chukwu', 'Mohammed', 'Balogun',
        'Anyanwu', 'Danjuma',
    ];

    /** @var array<int, string> */
    private const LECTURER_FIRST_NAMES = [
        'Adaeze', 'Ibrahim', 'Chinedu', 'Fatima', 'Emeka',
        'Halima', 'Olusegun', 'Chidinma',
    ];

    /** @var array<int, string> */
    private const LECTURER_LAST_NAMES = [
        'Nwosu', 'Aliyu', 'Okafor', 'Yusuf', 'Obi',
        'Adebayo', 'Ezenwa', 'Lawal',
    ];

    public function run(): void
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();
        $departments = Department::whereIn('code', self::DEPARTMENT_CODES)->get();

        if ($departments->isEmpty()) {
            $this->command->error('No departments found. Run the base seeders first: php artisan db:seed');

            return;
        }

        $venues = $this->seedVenues();

        foreach ($departments->values() as $departmentIndex => $department) {
            $lecturers = $this->seedLecturers($department, $departmentIndex);
            $students = $this->seedStudents($department, $departmentIndex);
            $courses = $this->seedCourses($department);
            $offerings = $this->seedOfferings($courses, $lecturers, $session);

            $this->seedTimetable($offerings, $venues);
            $this->seedEnrollmentsAndGrades($students, $offerings, $lecturers->first());
        }

        $this->recomputeGpaHistories();

        $this->report();
    }

    /* ----------------------------------------------------------- name / email */

    /**
     * A distinct full name for the nth person in a pool.
     *
     * The last name advances only once the first-name list has been exhausted,
     * so pairs stay unique for the first (first x last) people — 196 for
     * students, 64 for lecturers, comfortably above what is seeded.
     *
     * @param  array<int, string>  $firstNames
     * @param  array<int, string>  $lastNames
     */
    private function uniqueName(int $n, array $firstNames, array $lastNames): string
    {
        $first = $firstNames[$n % count($firstNames)];
        $last = $lastNames[intdiv($n, count($firstNames)) % count($lastNames)];

        return "{$first} {$last}";
    }

    /**
     * firstname.lastname@aust.edu.ng — the institutional convention, and
     * guessable from the name shown on screen.
     *
     * Uniqueness rests on uniqueName() producing distinct pairs; a collision
     * would surface immediately as a unique-constraint violation rather than
     * silently merging two people.
     */
    private function emailFor(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $slug = collect($parts)
            ->map(fn (string $part) => Str::slug($part))
            ->filter()
            ->implode('.');

        return "{$slug}@aust.edu.ng";
    }

    /* ---------------------------------------------------------------- venues */

    /**
     * @return Collection<int, Venue>
     */
    private function seedVenues(): Collection
    {
        $definitions = [
            ['code' => 'LT1', 'name' => 'Lecture Theatre 1', 'building' => 'NMI Building', 'type' => VenueType::LectureHall, 'capacity' => 250],
            ['code' => 'LT2', 'name' => 'Lecture Theatre 2', 'building' => 'NMI Building', 'type' => VenueType::LectureHall, 'capacity' => 180],
            ['code' => 'LT3', 'name' => 'Lecture Theatre 3', 'building' => 'Senate Building', 'type' => VenueType::LectureHall, 'capacity' => 120],
            ['code' => 'LAB1', 'name' => 'Software Lab 1', 'building' => 'Lab Complex', 'type' => VenueType::Laboratory, 'capacity' => 40],
            ['code' => 'LAB2', 'name' => 'Networks Lab', 'building' => 'Lab Complex', 'type' => VenueType::Laboratory, 'capacity' => 35],
            ['code' => 'SR1', 'name' => 'Seminar Room 1', 'building' => 'Postgraduate Block', 'type' => VenueType::SeminarRoom, 'capacity' => 30],
            ['code' => 'WS1', 'name' => 'Engineering Workshop', 'building' => 'Workshop Block', 'type' => VenueType::Workshop, 'capacity' => 50],
        ];

        return collect($definitions)->map(fn (array $venue) => Venue::firstOrCreate(
            ['code' => $venue['code']],
            [...$venue, 'is_active' => true],
        ));
    }

    /* ------------------------------------------------------------- lecturers */

    /**
     * @return Collection<int, User>
     */
    private function seedLecturers(Department $department, int $departmentIndex): Collection
    {
        $prefixes = ['Dr.', 'Prof.', 'Engr.', 'Dr.', 'Mrs.'];

        return collect(range(0, self::LECTURERS_PER_DEPARTMENT - 1))
            ->map(function (int $index) use ($department, $departmentIndex, $prefixes) {
                $name = $this->uniqueName(
                    $departmentIndex * self::LECTURERS_PER_DEPARTMENT + $index,
                    self::LECTURER_FIRST_NAMES,
                    self::LECTURER_LAST_NAMES,
                );

                $lecturer = User::firstOrCreate(
                    ['email' => $this->emailFor($name)],
                    [
                        'name' => $name,
                        'password' => Hash::make(self::PASSWORD),
                        'department_id' => $department->id,
                        'staff_id' => "{$department->code}/LEC/".str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'must_change_password' => false,
                        'email_verified_at' => now(),
                    ]
                );

                if (! $lecturer->hasRole('lecturer')) {
                    $lecturer->assignRole('lecturer');
                }

                $lecturer->lecturerProfile()->firstOrCreate([], [
                    'prefix' => $prefixes[$index % count($prefixes)],
                    'highest_qualification' => 'PhD '.$department->name,
                    'specialization' => $department->name,
                ]);

                return $lecturer;
            });
    }

    /* -------------------------------------------------------------- students */

    /**
     * Spread across entry years so every level filter (100–500) returns rows.
     *
     * @return Collection<int, User>
     */
    private function seedStudents(Department $department, int $departmentIndex): Collection
    {
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        return collect(range(0, self::STUDENTS_PER_DEPARTMENT - 1))
            ->map(function (int $index) use ($department, $departmentIndex, $session) {
                // 0..4 years back => levels 100 through 500.
                $entryYear = $session->start_year - ($index % 5);

                $name = $this->uniqueName(
                    $departmentIndex * self::STUDENTS_PER_DEPARTMENT + $index,
                    self::STUDENT_FIRST_NAMES,
                    self::STUDENT_LAST_NAMES,
                );

                $student = User::firstOrCreate(
                    ['email' => $this->emailFor($name)],
                    [
                        'name' => $name,
                        'password' => Hash::make(self::PASSWORD),
                        'department_id' => $department->id,
                        'study_type' => $index % 7 === 0 ? 'Postgraduate' : 'Undergraduate',
                        'entry_year' => $entryYear,
                        'student_id' => sprintf('%s/%dU/%06d', $department->code, $entryYear % 100, 100000 + $index + $department->id * 100),
                        'must_change_password' => false,
                        'email_verified_at' => now(),
                    ]
                );

                if (! $student->hasRole('student')) {
                    $student->assignRole('student');
                }

                $student->profile()->firstOrCreate([], [
                    'phone' => '080'.str_pad((string) random_int(10000000, 99999999), 8, '0'),
                    'address' => 'Block '.($index + 1).', Student Village, Abuja',
                    'date_of_birth' => now()->subYears(18 + ($index % 6))->toDateString(),
                    'emergency_contact_name' => Str::afterLast($name, ' ').' Family',
                    'emergency_contact_phone' => '081'.str_pad((string) random_int(10000000, 99999999), 8, '0'),
                ]);

                return $student;
            });
    }

    /* --------------------------------------------------------------- courses */

    /**
     * @return Collection<int, Course>
     */
    private function seedCourses(Department $department): Collection
    {
        $titles = [
            'Introduction to Programming', 'Discrete Mathematics', 'Data Structures',
            'Database Management Systems', 'Operating Systems', 'Computer Networks',
            'Software Engineering', 'Human Computer Interaction', 'Artificial Intelligence',
            'Research Methods',
        ];

        return collect($titles)->map(function (string $title, int $index) use ($department) {
            $level = (string) ((intdiv($index, 2) + 1) * 100);
            $code = sprintf('%s %d%02d', $department->code, intdiv($index, 2) + 1, ($index % 2) * 2 + 1);

            return Course::firstOrCreate(
                ['code' => $code],
                [
                    'department_id' => $department->id,
                    'title' => $title,
                    'credit_units' => [2, 3, 3, 4][$index % 4],
                    'level' => $level,
                    'semester' => $index % 2 === 0 ? Semester::First : Semester::Second,
                    'type' => $index % 4 === 3 ? CourseType::Elective : CourseType::Compulsory,
                    'description' => "{$title} for {$level} level students.",
                    // One inactive course per department so the filter and the
                    // deactivate/reactivate flow both have something to act on.
                    'is_active' => $index !== 9,
                ]
            );
        });
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, User>  $lecturers
     * @return Collection<int, CourseOffering>
     */
    private function seedOfferings(Collection $courses, Collection $lecturers, AcademicSession $session): Collection
    {
        return $courses
            ->filter(fn (Course $course) => $course->is_active)
            ->values()
            ->map(fn (Course $course, int $index) => CourseOffering::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'academic_session_id' => $session->id,
                    'semester' => $course->semester,
                ],
                [
                    'lecturer_id' => $lecturers[$index % $lecturers->count()]->id,
                    'is_active' => true,
                ]
            ));
    }

    /**
     * @param  Collection<int, CourseOffering>  $offerings
     * @param  Collection<int, Venue>  $venues
     */
    private function seedTimetable(Collection $offerings, Collection $venues): void
    {
        $days = DayOfWeek::cases();
        $startHours = [8, 10, 13, 15];

        $offerings->each(function (CourseOffering $offering, int $index) use ($days, $startHours, $venues) {
            $start = $startHours[$index % count($startHours)];

            TimetableSlot::firstOrCreate(
                [
                    'course_offering_id' => $offering->id,
                    'day' => $days[$index % count($days)],
                ],
                [
                    'venue_id' => $venues[$index % $venues->count()]->id,
                    'start_time' => sprintf('%02d:00:00', $start),
                    'end_time' => sprintf('%02d:00:00', $start + 2),
                    'is_active' => true,
                ]
            );
        });
    }

    /* ------------------------------------------------- enrollments & grades */

    /**
     * Fans students out across every enrollment and grade state.
     *
     * @param  Collection<int, User>  $students
     * @param  Collection<int, CourseOffering>  $offerings
     */
    private function seedEnrollmentsAndGrades(Collection $students, Collection $offerings, User $lecturer): void
    {
        if ($offerings->isEmpty()) {
            return;
        }

        $students->each(function (User $student, int $index) use ($offerings, $lecturer) {
            // Each student takes four courses, offset so cohorts differ.
            $taken = $offerings->slice($index % 3, 4)->values();

            if ($taken->isEmpty()) {
                $taken = $offerings->take(4);
            }

            // Rotate through states so every tab on every review screen is
            // populated: pending -> approved -> rejected -> approved ...
            $status = match ($index % 4) {
                0 => EnrollmentStatus::Pending,
                2 => EnrollmentStatus::Rejected,
                default => EnrollmentStatus::Active,
            };

            $taken->each(function (CourseOffering $offering, int $courseIndex) use ($student, $status, $lecturer, $index) {
                $enrollment = Enrollment::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_offering_id' => $offering->id,
                    ],
                    ['status' => $status]
                );

                // Only active enrollments can carry a grade.
                if ($status !== EnrollmentStatus::Active) {
                    return;
                }

                $gradeStatus = match (($index + $courseIndex) % 4) {
                    0 => GradeStatus::Draft,
                    1 => GradeStatus::Pending,
                    2 => GradeStatus::Approved,
                    default => GradeStatus::Rejected,
                };

                $this->seedGrade($enrollment, $lecturer, $gradeStatus, $index + $courseIndex);
            });
        });
    }

    private function seedGrade(Enrollment $enrollment, User $lecturer, GradeStatus $status, int $seed): void
    {
        if (Grade::where('enrollment_id', $enrollment->id)->exists()) {
            return;
        }

        $ca = 10 + ($seed % 11);          // 10–20
        $project = 8 + ($seed % 13);      // 8–20
        $exam = 25 + ($seed % 36);        // 25–60
        $total = $ca + $project + $exam;

        $resolved = $status === GradeStatus::Draft
            ? ['letter_grade' => null, 'grade_point' => null]
            : app(GradeService::class)->resolveGrade((float) $total);

        Grade::create([
            'enrollment_id' => $enrollment->id,
            'submitted_by' => $lecturer->id,
            'ca_score' => $ca,
            'project_score' => $project,
            'exam_score' => $exam,
            'score' => $total,
            'letter_grade' => $resolved['letter_grade'],
            'grade_point' => $resolved['grade_point'],
            'status' => $status,
            'rejection_reason' => $status === GradeStatus::Rejected
                ? 'Exam scores do not match the signed mark sheet. Please re-check and resubmit.'
                : null,
            'submitted_at' => $status === GradeStatus::Draft ? null : now()->subDays($seed % 14),
            'approved_at' => $status === GradeStatus::Approved ? now()->subDays($seed % 7) : null,
            'approved_by' => $status === GradeStatus::Approved
                ? User::role('admin')->value('id')
                : null,
        ]);
    }

    /**
     * Builds each student's GPA history through the real service, so the
     * numbers on screen are internally consistent rather than invented.
     */
    private function recomputeGpaHistories(): void
    {
        $service = app(GradeService::class);

        $studentIds = Grade::query()
            ->where('grades.status', GradeStatus::Approved->value)
            ->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')
            ->distinct()
            ->pluck('enrollments.student_id');

        $studentIds->each(fn (int $studentId) => $service->recomputeStudentHistory($studentId));

        $this->command->info("Recomputed GPA history for {$studentIds->count()} students.");
    }

    private function report(): void
    {
        $this->command->newLine();
        $this->command->info('Development data seeded.');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Students', User::role('student')->count()],
                ['Lecturers', User::role('lecturer')->count()],
                ['Courses', Course::count()],
                ['Course offerings', CourseOffering::count()],
                ['Venues', Venue::count()],
                ['Timetable slots', TimetableSlot::count()],
                ['Enrollments — pending', Enrollment::where('status', EnrollmentStatus::Pending)->count()],
                ['Enrollments — active', Enrollment::where('status', EnrollmentStatus::Active)->count()],
                ['Enrollments — rejected', Enrollment::where('status', EnrollmentStatus::Rejected)->count()],
                ['Grades — draft', Grade::where('status', GradeStatus::Draft)->count()],
                ['Grades — pending', Grade::where('status', GradeStatus::Pending)->count()],
                ['Grades — approved', Grade::where('status', GradeStatus::Approved)->count()],
                ['Grades — rejected', Grade::where('status', GradeStatus::Rejected)->count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('Every seeded account uses the password: '.self::PASSWORD);
        $this->command->info('Emails follow firstname.lastname@aust.edu.ng — sample logins:');

        User::role('student')->orderBy('id')->limit(3)->get()
            ->each(fn (User $user) => $this->command->line("  student   {$user->email}  ({$user->name})"));

        User::role('lecturer')->orderBy('id')->limit(2)->get()
            ->each(fn (User $user) => $this->command->line("  lecturer  {$user->email}  ({$user->name})"));

        $this->command->line('  admin     admin@aust.edu.ng / Admin@1234');
    }
}
