<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseOfferingRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Illuminate\Http\JsonResponse;

class CourseOfferingController extends Controller
{
    public function index(): JsonResponse
    {
        $offerings = CourseOffering::with('course.department', 'academicSession', 'lecturer')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Course offerings retrieved successfully.',
            'data'    => CourseOfferingResource::collection($offerings->items()),
            'meta'    => [
                'current_page' => $offerings->currentPage(),
                'last_page'    => $offerings->lastPage(),
                'per_page'     => $offerings->perPage(),
                'total'        => $offerings->total(),
            ],
        ]);
    }

    public function store(StoreCourseOfferingRequest $request): JsonResponse
    {
        $offering = CourseOffering::create($request->validated());
        $offering->load('course.department', 'academicSession', 'lecturer');

        return response()->json([
            'success' => true,
            'message' => 'Course offering created successfully.',
            'data'    => new CourseOfferingResource($offering),
        ], 201);
    }

    public function show(CourseOffering $offering): JsonResponse
    {
        $offering->load('course.department', 'academicSession', 'lecturer');

        return response()->json([
            'success' => true,
            'message' => 'Course offering retrieved successfully.',
            'data'    => new CourseOfferingResource($offering),
        ]);
    }
}