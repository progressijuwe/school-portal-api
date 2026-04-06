<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::with('department')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Courses retrieved successfully.',
            'data'    => CourseResource::collection($courses->items()),
            'meta'    => [
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
                'per_page'     => $courses->perPage(),
                'total'        => $courses->total(),
            ],
        ]);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'data'    => new CourseResource($course),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course retrieved successfully.',
            'data'    => new CourseResource($course),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $course->update($request->validated());
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'data'    => new CourseResource($course),
        ]);
    }

    public function deactivate(Course $course): JsonResponse
    {
        if (! $course->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Course is already inactive.',
            ], 409);
        }

        if ($course->offerings()->where('is_active', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate a course with active offerings.',
            ], 409);
        }

        $course->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Course deactivated successfully.',
        ]);
    }

    public function activate(Course $course): JsonResponse
    {
        if ($course->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Course is already active.',
            ], 409);
        }

        $course->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Course activated successfully.',
        ]);
    }
}