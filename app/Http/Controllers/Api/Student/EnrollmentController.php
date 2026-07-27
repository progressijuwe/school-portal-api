<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\EnrollmentResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends BaseController
{
    // Offerings the student can register for (current session/semester, their department/level, not already enrolled)
    public function availableOfferings(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $semester = $request->query('semester', 'first');

        $enrolledOfferingIds = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'active'])
            ->pluck('course_offering_id');

        $offerings = CourseOffering::where('academic_session_id', $session->id)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->whereNotIn('id', $enrolledOfferingIds)
            ->whereHas('course', fn($q) =>
                $q->where('department_id', $student->department_id)
                  ->where('is_active', true)
            )
            ->with(['course', 'lecturer', 'academicSession'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Available offerings retrieved successfully.',
            'data'    => CourseOfferingResource::collection($offerings),
        ]);
    }

    // Submit registration — creates pending enrollments, enforces max credit units
    public function store(Request $request): JsonResponse
    {
        $student = $request->user();

        $validated = $request->validate([
            'course_offering_ids'   => ['required', 'array', 'min:1'],
            'course_offering_ids.*' => ['required', 'integer', 'exists:course_offerings,id'],
        ]);

        $offeringIds = $validated['course_offering_ids'];

        $offerings = CourseOffering::whereIn('id', $offeringIds)
            ->with('course')
            ->get();

        // Check for existing pending/active enrollments in the same offerings
        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->whereIn('course_offering_id', $offeringIds)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($alreadyEnrolled) {
            throw ValidationException::withMessages([
                'course_offering_ids' => ['You are already registered or pending registration for one or more selected courses.'],
            ]);
        }

        // Enforce max credit units — includes existing pending/active enrollments for the same semester
        $semester = $offerings->first()?->semester;
        $sessionId = $offerings->first()?->academic_session_id;

        $existingCredits = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'active'])
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $sessionId)
                  ->where('semester', $semester)
            )
            ->with('courseOffering.course')
            ->get()
            ->sum(fn($e) => $e->courseOffering->course->credit_units);

        $newCredits = $offerings->sum('course.credit_units');
        $maxCredits = config('academics.max_credit_units_per_semester');

        if (($existingCredits + $newCredits) > $maxCredits) {
            throw ValidationException::withMessages([
                'course_offering_ids' => ["This registration would exceed the maximum of {$maxCredits} credit units for the semester (currently at {$existingCredits}, attempting to add {$newCredits})."],
            ]);
        }

        $minCredits = config('academics.min_credit_units_per_semester');
        $totalCredits = $existingCredits + $newCredits;

        if ($totalCredits > $maxCredits) {
            throw ValidationException::withMessages([
                'course_offering_ids' => ["This registration would exceed the maximum of {$maxCredits} credit units for the semester (currently at {$existingCredits}, attempting to add {$newCredits})."],
            ]);
        }

        if ($totalCredits < $minCredits) {
            throw ValidationException::withMessages([
                'course_offering_ids' => ["This registration falls below the minimum of {$minCredits} credit units required for the semester (currently at {$totalCredits})."],
            ]);
        }

        $enrollmentIds = collect($offeringIds)->map(fn($offeringId) =>
            Enrollment::create([
                'student_id'         => $student->id,
                'course_offering_id' => $offeringId,
                'status'             => 'pending',
            ])->id
        );

        $enrollments = Enrollment::whereIn('id', $enrollmentIds)
            ->with('courseOffering.course', 'courseOffering.academicSession')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Course registration submitted for approval.',
            'data'    => EnrollmentResource::collection($enrollments),
        ], 201);
    }

    // Student's own registrations (any status) — useful to show pending/rejected state
    public function myEnrollments(Request $request): JsonResponse
    {
        $student = $request->user();
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $semester = $request->query('semester', 'first');

        $enrollments = Enrollment::where('student_id', $student->id)
            ->whereHas('courseOffering', fn($q) =>
                $q->where('academic_session_id', $session->id)
                  ->where('semester', $semester)
            )
            ->with('courseOffering.course', 'courseOffering.lecturer', 'courseOffering.academicSession')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Registrations retrieved successfully.',
            'data'    => EnrollmentResource::collection($enrollments),
        ]);
    }
}