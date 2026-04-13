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
}