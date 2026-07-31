<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\EnrollStudentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends BaseController
{
    /**
     * @var array<int, string>
     */
    private const RESPONSE_RELATIONS = [
        'student',
        'courseOffering.course',
        'courseOffering.academicSession',
    ];

    public function store(EnrollStudentRequest $request): JsonResponse
    {
        // An admin enrolling a student directly bypasses the approval queue.
        $enrollment = Enrollment::create([
            'student_id' => $request->integer('student_id'),
            'course_offering_id' => $request->integer('course_offering_id'),
            'status' => EnrollmentStatus::Active,
        ]);

        $enrollment->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Student enrolled successfully.',
            'data' => new EnrollmentResource($enrollment),
        ], 201);
    }

    public function pending(): JsonResponse
    {
        $enrollments = Enrollment::where('status', EnrollmentStatus::Pending)
            ->with(self::RESPONSE_RELATIONS)
            ->latest()
            ->paginate(20);

        return $this->paginated(
            $enrollments,
            EnrollmentResource::class,
            'Pending registrations retrieved successfully.'
        );
    }

    public function approve(Enrollment $enrollment): JsonResponse
    {
        return $this->transition($enrollment, EnrollmentStatus::Active, 'approved');
    }

    public function reject(Enrollment $enrollment): JsonResponse
    {
        return $this->transition($enrollment, EnrollmentStatus::Rejected, 'rejected');
    }

    public function drop(Enrollment $enrollment): JsonResponse
    {
        if ($enrollment->status === EnrollmentStatus::Dropped) {
            return response()->json([
                'success' => false,
                'message' => 'This enrollment has already been dropped.',
            ], 409);
        }

        $enrollment->update(['status' => EnrollmentStatus::Dropped]);
        $enrollment->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment dropped successfully.',
            'data' => new EnrollmentResource($enrollment),
        ]);
    }

    /**
     * Approve and reject share one state machine: only a pending registration
     * can move. Both return the updated resource so the frontend can patch its
     * cache in place instead of refetching the whole queue.
     */
    private function transition(Enrollment $enrollment, EnrollmentStatus $to, string $verb): JsonResponse
    {
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            return response()->json([
                'success' => false,
                'message' => "Only pending registrations can be {$verb}.",
            ], 409);
        }

        $enrollment->update(['status' => $to]);
        $enrollment->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => "Registration {$verb} successfully.",
            'data' => new EnrollmentResource($enrollment),
        ]);
    }
}
