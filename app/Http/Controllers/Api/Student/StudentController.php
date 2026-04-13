<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\GpaRecordResource;
use App\Http\Resources\GradeResource;
use App\Http\Resources\TimetableSlotResource;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;

class StudentController extends BaseController
{
    // Dashboard
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        // Enrolled courses count for current session
        $enrolledCount = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $session->id)
            )
            ->count();

        // Latest GPA records
        $firstSemesterGpa  = GpaRecord::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('semester', 'first')
            ->first();

        $secondSemesterGpa = GpaRecord::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('semester', 'second')
            ->first();

        // Overall CGPA — latest GPA record has the most up to date CGPA
        $latestGpa = GpaRecord::where('student_id', $student->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard retrieved successfully.',
            'data'    => [
                'student'         => [
                    'id'         => $student->id,
                    'name'       => $student->name,
                    'student_id' => $student->student_id,
                    'department' => $student->department?->name,
                    'level'      => $student->entry_year
                                        ? $this->resolveLevel($student->entry_year)
                                        : null,
                ],
                'session'         => [
                    'id'         => $session->id,
                    'name'       => $session->name,
                    'is_current' => $session->is_current,
                ],
                'enrolled_courses'    => $enrolledCount,
                'first_semester_gpa'  => $firstSemesterGpa?->gpa,
                'second_semester_gpa' => $secondSemesterGpa?->gpa,
                'cgpa'                => $latestGpa?->cgpa ?? '0.00',
            ],
        ]);
    }

    // Courses

    public function courses(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $session->id)
            )
            ->with([
                'courseOffering.course.department',
                'courseOffering.lecturer',
                'courseOffering.academicSession',
            ])
            ->get();

        $courses = $enrollments->map(fn($enrollment) => [
            'enrollment_id' => $enrollment->id,
            'status'        => $enrollment->status,
            'offering'      => new CourseOfferingResource($enrollment->courseOffering),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully.',
            'data'    => [
                'session' => $session->name,
                'courses' => $courses,
            ],
        ]);
    }

    // Timetable 

    public function timetable(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        // Get all course offering IDs the student is enrolled in
        $offeringIds = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $session->id)
            )
            ->pluck('course_offering_id');

        $slots = TimetableSlot::whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->with('courseOffering.course', 'courseOffering.lecturer', 'venue')
            ->orderByRaw("CASE day
                WHEN 'monday'    THEN 1
                WHEN 'tuesday'   THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday'  THEN 4
                WHEN 'friday'    THEN 5
            END")
            ->orderBy('start_time')
            ->get();

        // Group by day for easier frontend rendering
        $grouped = $slots->groupBy('day')->map(fn($daySlots) =>
            TimetableSlotResource::collection($daySlots)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable retrieved successfully.',
            'data'    => [
                'session'   => $session->name,
                'timetable' => $grouped,
            ],
        ]);
    }

    // Grades 

    public function grades(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $session->id)
            )
            ->with([
                'courseOffering.course',
                'courseOffering.academicSession',
                'grade' => fn($q) => $q->where('status', 'approved'),
            ])
            ->get();

        $grades = $enrollments->map(fn($enrollment) => [
            'course'        => [
                'title'        => $enrollment->courseOffering->course->title,
                'code'         => $enrollment->courseOffering->course->code,
                'credit_units' => $enrollment->courseOffering->course->credit_units,
                'semester'     => $enrollment->courseOffering->semester,
            ],
            'grade'         => $enrollment->grade
                                ? new GradeResource($enrollment->grade)
                                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Grades retrieved successfully.',
            'data'    => [
                'session' => $session->name,
                'grades'  => $grades,
            ],
        ]);
    }

    // GPA Records 

    public function gpaRecords(Request $request): JsonResponse
    {
        $student = $request->user();

        $records = GpaRecord::where('student_id', $student->id)
            ->with('academicSession')
            ->latest()
            ->get();

        // Latest record has the most up to date CGPA
        $cgpa = $records->first()?->cgpa ?? '0.00';

        return response()->json([
            'success' => true,
            'message' => 'GPA records retrieved successfully.',
            'data'    => [
                'cgpa'    => $cgpa,
                'records' => GpaRecordResource::collection($records),
            ],
        ]);
    }

    // Notifications
    public function notifications(Request $request): JsonResponse
    {
        return $this->notificationResponse($request);
    }

    public function markNotificationRead(Request $request, string $notificationId): JsonResponse
    {
        return $this->markReadResponse($request, $notificationId);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        return $this->markAllReadResponse($request);
    }

    // Helpers

    protected function resolveLevel(int $entryYear): string
    {
        $yearsElapsed = now()->year - $entryYear;
        $level        = ($yearsElapsed * 100) + 100;

        // Cap at 500
        return min($level, 500) . ' Level';
    }
}