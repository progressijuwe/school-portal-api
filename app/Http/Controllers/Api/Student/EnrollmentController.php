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
use Illuminate\Database\Eloquent\Collection;
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

            /*
             * upsert, not insert.
             *
             * (student_id, course_offering_id) is unique, and a rejected
             * enrollment keeps its row — so a student re-registering for a
             * course after their submission was turned down collided with the
             * old row and got a raw SQLSTATE 23000 in the response body. The
             * one case the "register once per semester" rule exists to allow
             * was the one case that crashed.
             *
             * created_at is refreshed alongside status because a resubmission
             * is a new submission: leaving the original timestamp would have
             * the registration date report when the rejected attempt was made.
             */
            Enrollment::upsert(
                array_map(fn (int $offeringId) => [
                    'student_id' => $student->id,
                    'course_offering_id' => $offeringId,
                    'status' => EnrollmentStatus::Pending->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $offeringIds),
                ['student_id', 'course_offering_id'],
                ['status', 'created_at', 'updated_at'],
            );

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
            'data' => [
                'enrollments' => EnrollmentResource::collection($enrollments),
                'registration' => $this->summariseRegistration($enrollments),
            ],
        ]);
    }

    /**
     * Where the student stands for this semester, decided server-side.
     *
     * The registration page needs to know whether to offer a course picker at
     * all. Deciding that in the browser would mean reimplementing the rule the
     * API enforces, and the two would eventually disagree — so the same
     * `occupyingSeat` scope that blocks a second submission is what produces
     * this summary.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<string, mixed>
     */
    private function summariseRegistration(Collection $enrollments): array
    {
        $occupying = $enrollments->filter(
            fn (Enrollment $enrollment) => in_array(
                $enrollment->status->value,
                EnrollmentStatus::occupyingSeat(),
                true,
            )
        );

        $hasRegistered = $occupying->isNotEmpty();

        $status = match (true) {
            // Pending outranks active in the summary: while any part of the
            // submission is still awaiting review, the registration as a whole
            // has not been decided.
            $occupying->contains(fn (Enrollment $e) => $e->status === EnrollmentStatus::Pending) => 'pending',
            $hasRegistered => 'approved',
            $enrollments->contains(fn (Enrollment $e) => $e->status === EnrollmentStatus::Rejected) => 'rejected',
            default => 'none',
        };

        return [
            'status' => $status,
            'has_registered' => $hasRegistered,
            // A rejected submission leaves nothing occupying a seat, so the
            // student is free to register again.
            'can_register' => ! $hasRegistered,
            'course_count' => $occupying->count(),
            'total_credit_units' => (int) $occupying->sum(
                fn (Enrollment $enrollment) => $enrollment->courseOffering->course->credit_units
            ),
            'submitted_at' => $occupying->min('created_at')?->toDateTimeString(),
        ];
    }
}
