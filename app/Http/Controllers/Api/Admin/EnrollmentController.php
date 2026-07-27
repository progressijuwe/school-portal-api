<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnrollStudentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function store(EnrollStudentRequest $request): JsonResponse
    {
        $enrollment = Enrollment::create([
            'student_id'         => $request->student_id,
            'course_offering_id' => $request->course_offering_id,
            'status'             => 'active',
        ]);

        $enrollment->load('student', 'courseOffering.course', 'courseOffering.academicSession');

        return response()->json([
            'success' => true,
            'message' => 'Student enrolled successfully.',
            'data'    => new EnrollmentResource($enrollment),
        ], 201);
    }

    public function drop(Enrollment $enrollment): JsonResponse
    {
        if ($enrollment->status === 'dropped') {
            return response()->json([
                'success' => false,
                'message' => 'This enrollment has already been dropped.',
            ], 409);
        }

        $enrollment->update(['status' => 'dropped']);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment dropped successfully.',
        ]);
    }

    public function pending(): JsonResponse
    {
        $enrollments = Enrollment::where('status', 'pending')
            ->with('student', 'courseOffering.course', 'courseOffering.academicSession')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Pending registrations retrieved successfully.',
            'data'    => EnrollmentResource::collection($enrollments),
            'meta'    => [
                'current_page' => $enrollments->currentPage(),
                'last_page'    => $enrollments->lastPage(),
                'total'        => $enrollments->total(),
            ],
        ]);
    }

    public function approve(Enrollment $enrollment): JsonResponse
    {
        if ($enrollment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending registrations can be approved.',
            ], 409);
        }

        $enrollment->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Registration approved successfully.',
        ]);
    }

    public function reject(Enrollment $enrollment): JsonResponse
    {
        if ($enrollment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending registrations can be rejected.',
            ], 409);
        }

        $enrollment->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => 'Registration rejected.',
        ]);
    }
}