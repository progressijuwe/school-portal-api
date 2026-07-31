<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\Semester;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Student\StoreEnrollmentRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\EnrollmentResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends BaseController
{
    /**
     * Offerings the student may still register for this semester.
     */
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

        $semester = $this->resolveSemester($request, $session);

        $enrolledOfferingIds = Enrollment::where('student_id', $student->id)
            ->occupyingSeat()
            ->pluck('course_offering_id');

        $offerings = CourseOffering::query()
            ->where('academic_session_id', $session->id)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->whereNotIn('id', $enrolledOfferingIds)
            ->whereHas('course', fn ($query) => $query
                ->where('department_id', $student->department_id)
                ->where('is_active', true))
            ->with(['course', 'lecturer', 'academicSession'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Available offerings retrieved successfully.',
            'data' => CourseOfferingResource::collection($offerings),
        ]);
    }

    /**
     * Submit a course registration.
     *
     * All the rules live in StoreEnrollmentRequest. The insert runs inside a
     * transaction that locks the student's existing enrollments first, so two
     * concurrent submissions cannot both pass the credit-limit check and both
     * write — the previous version validated and then inserted row by row with
     * no isolation at all.
     */
    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $student = $request->user();
        $offeringIds = $request->offeringIds();

        $enrollments = DB::transaction(function () use ($student, $offeringIds) {
            Enrollment::where('student_id', $student->id)
                ->lockForUpdate()
                ->get();

            $now = now();

            Enrollment::insert(array_map(fn (int $offeringId) => [
                'student_id' => $student->id,
                'course_offering_id' => $offeringId,
                'status' => EnrollmentStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $offeringIds));

            return Enrollment::where('student_id', $student->id)
                ->whereIn('course_offering_id', $offeringIds)
                ->with('courseOffering.course', 'courseOffering.academicSession')
                ->get();
        });

        return response()->json([
            'success' => true,
            'message' => 'Course registration submitted for approval.',
            'data' => EnrollmentResource::collection($enrollments),
        ], 201);
    }

    /**
     * The student's own registrations for a period, any status.
     */
    public function myEnrollments(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found.',
            ], 404);
        }

        $enrollments = Enrollment::where('student_id', $request->user()->id)
            ->forPeriod($session->id, $this->resolveSemester($request, $session))
            ->with('courseOffering.course', 'courseOffering.lecturer', 'courseOffering.academicSession')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Registrations retrieved successfully.',
            'data' => EnrollmentResource::collection($enrollments),
        ]);
    }
}
