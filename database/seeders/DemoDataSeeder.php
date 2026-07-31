<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'SOF')->firstOrFail();
        $session = AcademicSession::where('is_current', true)->firstOrFail();

        $student = User::where('email', 'student1@aust.edu.ng')->firstOrFail();
        $lecturer = User::where('email', 'lecturer1@aust.edu.ng')->firstOrFail();

        $venue = Venue::firstOrCreate(
            ['code' => 'LT1'],
            [
                'name' => 'Lecture Theatre 1',
                'building' => 'NMI Building',
                'type' => 'lecture_hall',
                'capacity' => 100,
                'is_active' => true,
            ]
        );

        $venue2 = Venue::firstOrCreate(
            ['code' => 'LAB1'],
            [
                'name' => 'Software Lab 1',
                'building' => 'Lab Building',
                'type' => 'laboratory',
                'capacity' => 40,
                'is_active' => true,
            ]
        );

        // ── Courses ──
        $courses = [
            [
                'code' => 'SEN 406',
                'title' => 'Human Computer Interaction',
                'credit_units' => 3,
                'level' => '400',
                'semester' => 'second',
                'type' => 'compulsory',
                'day' => 'monday',
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'venue' => $venue,
            ],
            [
                'code' => 'SEN 402',
                'title' => 'Software Construction',
                'credit_units' => 3,
                'level' => '400',
                'semester' => 'second',
                'type' => 'compulsory',
                'day' => 'thursday',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'venue' => $venue2,
            ],
            [
                'code' => 'SEN 404',
                'title' => 'Object Oriented Analysis and Design',
                'credit_units' => 3,
                'level' => '400',
                'semester' => 'second',
                'type' => 'compulsory',
                'day' => 'friday',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'venue' => $venue,
            ],
        ];

        foreach ($courses as $data) {
            $course = Course::firstOrCreate(
                ['code' => $data['code']],
                [
                    'title' => $data['title'],
                    'credit_units' => $data['credit_units'],
                    'level' => $data['level'],
                    'semester' => $data['semester'],
                    'type' => $data['type'],
                    'description' => "{$data['title']} course.",
                    'is_active' => true,
                    'department_id' => $department->id,
                ]
            );

            $offering = CourseOffering::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'academic_session_id' => $session->id,
                ],
                [
                    'semester' => $data['semester'],
                    'is_active' => true,
                    'lecturer_id' => $lecturer->id,
                ]
            );

            Enrollment::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'course_offering_id' => $offering->id,
                ],
                ['status' => 'active']
            );

            TimetableSlot::firstOrCreate(
                [
                    'course_offering_id' => $offering->id,
                    'day' => $data['day'],
                ],
                [
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'venue_id' => $data['venue']->id,
                    'is_active' => true,
                ]
            );
        }

        // ── GPA history across 3 real sessions ──
        $pastSessions = AcademicSession::orderBy('start_year')->get();

        $gpaHistory = [
            ['session' => '2023/2024', 'semester' => 'first',  'gpa' => 3.50, 'cgpa' => 3.50, 'credits' => 16, 'cum_credits' => 16],
            ['session' => '2023/2024', 'semester' => 'second', 'gpa' => 3.60, 'cgpa' => 3.55, 'credits' => 17, 'cum_credits' => 33],
            ['session' => '2024/2025', 'semester' => 'first',  'gpa' => 3.75, 'cgpa' => 3.62, 'credits' => 18, 'cum_credits' => 51],
            ['session' => '2024/2025', 'semester' => 'second', 'gpa' => 3.89, 'cgpa' => 3.68, 'credits' => 18, 'cum_credits' => 69],
            ['session' => '2025/2026', 'semester' => 'first',  'gpa' => 3.72, 'cgpa' => 3.70, 'credits' => 17, 'cum_credits' => 86],
            ['session' => '2025/2026', 'semester' => 'second', 'gpa' => 3.64, 'cgpa' => 3.77, 'credits' => 18, 'cum_credits' => 104],
        ];

        foreach ($gpaHistory as $i => $record) {
            $recordSession = $pastSessions->firstWhere('name', $record['session']);

            GpaRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_session_id' => $recordSession->id,
                    'semester' => $record['semester'],
                ],
                [
                    'gpa' => $record['gpa'],
                    'cgpa' => $record['cgpa'],
                    'total_credit_units' => $record['credits'],
                    'total_grade_points' => round($record['gpa'] * $record['credits'], 2),
                    'cumulative_credit_units' => $record['cum_credits'],
                    'cumulative_grade_points' => round($record['cgpa'] * $record['cum_credits'], 2),
                    'created_at' => now()->subMonths((6 - $i) * 4),
                ]
            );
        }

        $this->command->info('Demo data seeded: 3 courses, offerings, enrollments, timetable slots, 6 GPA records.');
    }
}
