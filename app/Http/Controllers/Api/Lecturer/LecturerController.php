<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\TimetableSlotResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LecturerController extends BaseController
{
    // Dashboard

    public function dashboard(Request $request): JsonResponse
    {
        $lecturer = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $offerings = CourseOffering::where('lecturer_id', $lecturer->id)
            ->where('academic_session_id', $session->id)
            ->with('course')
            ->get();

        $offeringIds = $offerings->pluck('id');
        $totalStudents = Enrollment::whereIn('course_offering_id', $offeringIds)
            ->where('status', 'active')
            ->count();

        $pendingGrades = Grade::whereHas('enrollment.courseOffering', fn ($q) => $q->where('lecturer_id', $lecturer->id)
            ->where('academic_session_id', $session->id)
        )
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard retrieved successfully.',
            'data' => [
                'lecturer' => [
                    'id' => $lecturer->id,
                    'name' => $lecturer->name,
                    'staff_id' => $lecturer->staff_id,
                    'department' => $lecturer->department?->name,
                    'department_code' => $lecturer->department?->code,
                ],
                'session' => [
                    'id' => $session->id,
                    'name' => $session->name,
                    'is_current' => $session->is_current,
                ],
                'total_courses' => $offerings->count(),
                'total_students' => $totalStudents,
                'pending_grades' => $pendingGrades,
            ],
        ]);
    }

    // Courses

    public function courses(Request $request): JsonResponse
    {
        $lecturer = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $offerings = CourseOffering::where('lecturer_id', $lecturer->id)
            ->where('academic_session_id', $session->id)
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
            ->with('course.department', 'academicSession')
            ->get();

        $courses = $offerings->map(fn ($offering) => [
            'offering' => new CourseOfferingResource($offering),
            'enrolled_count' => $offering->enrollments_count,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully.',
            'data' => [
                'session' => $session->name,
                'courses' => $courses,
            ],
        ]);
    }

    // Students

    public function students(Request $request, CourseOffering $offering): JsonResponse
    {
        $lecturer = $request->user();

        if ($offering->lecturer_id !== $lecturer->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this course offering.',
            ], 403);
        }

        // The grade relation is loaded unfiltered. It is a hasOne over a unique
        // enrollment_id, so a status filter could only ever hide that one row,
        // never choose between several — and the previous filter listed every
        // status except `rejected`, so once an admin sent a mark sheet back the
        // lecturer's own scores and the rejection reason vanished from their
        // screen and the correction could not be made.
        //
        // The page size is large because a mark sheet is one document, not a
        // paged list: "Submit Results" posts every row the lecturer filled in,
        // so a page that hid part of the class would silently grade only the
        // visible portion. The ceiling matches the batch endpoint's own
        // `max:500` rule on the grades array, so the two cannot drift apart.
        $enrollments = Enrollment::where('course_offering_id', $offering->id)
            ->where('status', 'active')
            ->with(['student.department', 'grade'])
            ->join('users', 'users.id', '=', 'enrollments.student_id')
            ->orderBy('users.name')
            ->select('enrollments.*')
            ->paginate(perPage: min($request->integer('per_page', 100), 500));

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully.',
            'data' => [
                'course' => [
                    'title' => $offering->course->title,
                    'code' => $offering->course->code,
                ],
                'students' => EnrollmentResource::collection($enrollments->items()),
            ],
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    // Timetable

    public function timetable(Request $request): JsonResponse
    {
        $lecturer = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $offeringIds = CourseOffering::where('lecturer_id', $lecturer->id)
            ->where('academic_session_id', $session->id)
            ->pluck('id');

        $slots = TimetableSlot::whereIn('course_offering_id', $offeringIds)
            ->where('is_active', true)
            ->with('courseOffering.course', 'venue')
            ->orderByRaw("CASE day
                WHEN 'monday'    THEN 1
                WHEN 'tuesday'   THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday'  THEN 4
                WHEN 'friday'    THEN 5
            END")
            ->orderBy('start_time')
            ->get();

        $grouped = $slots->groupBy('day')->map(fn ($daySlots) => TimetableSlotResource::collection($daySlots)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable retrieved successfully.',
            'data' => [
                'session' => $session->name,
                'timetable' => $grouped,
            ],
        ]);
    }
}
