<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreCourseOfferingRequest;
use App\Http\Requests\Admin\UpdateCourseOfferingRequest;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseOfferingController extends BaseController
{
    /**
     * Relations every offering response needs. An offering means nothing
     * without its course and session, and the admin list is unreadable without
     * the lecturer, so they are named once rather than per endpoint.
     *
     * @var array<int, string>
     */
    private const RESPONSE_RELATIONS = [
        'course.department',
        'academicSession',
        'lecturer.lecturerProfile',
    ];

    /**
     * Searching and filtering happen in the database.
     *
     * The previous version returned every offering ever created, newest first,
     * with no filters at all — by the third academic session the offerings for
     * the semester actually being administered are buried pages deep.
     */
    public function index(Request $request): JsonResponse
    {
        $offerings = CourseOffering::with(self::RESPONSE_RELATIONS)
            ->withCount(['enrollments' => fn ($query) => $query
                ->where('status', EnrollmentStatus::Active->value)])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->whereHas('course', fn ($course) => $course
                    ->where(fn ($inner) => $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")));
            })
            ->when($request->filled('session_id'), fn ($query) => $query
                ->where('academic_session_id', $request->integer('session_id')))
            ->when($request->filled('semester'), fn ($query) => $query
                ->where('semester', $request->string('semester')->toString()))
            ->when($request->filled('department_id'), fn ($query) => $query
                ->whereHas('course', fn ($course) => $course
                    ->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('faculty_id'), fn ($query) => $query
                ->whereHas('course.department', fn ($department) => $department
                    ->where('faculty_id', $request->integer('faculty_id'))))
            // "Unassigned" is a filter value of its own rather than a
            // lecturer_id, because an offering with nobody teaching it is the
            // state an admin most needs to find and it has no id to match on.
            ->when(
                $request->input('lecturer_id') === 'unassigned',
                fn ($query) => $query->whereNull('lecturer_id'),
                fn ($query) => $query->when($request->filled('lecturer_id'), fn ($inner) => $inner
                    ->where('lecturer_id', $request->integer('lecturer_id')))
            )
            ->when($request->filled('is_active'), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate(perPage: min($request->integer('per_page', 20), 50))
            ->withQueryString();

        return $this->paginated(
            $offerings,
            CourseOfferingResource::class,
            'Course offerings retrieved successfully.'
        );
    }

    public function store(StoreCourseOfferingRequest $request): JsonResponse
    {
        $offering = CourseOffering::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course offering created successfully.',
            'data' => new CourseOfferingResource($this->hydrate($offering)),
        ], 201);
    }

    public function show(CourseOffering $offering): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Course offering retrieved successfully.',
            'data' => new CourseOfferingResource($this->hydrate($offering)),
        ]);
    }

    /**
     * Reassign the lecturer, or open and close the offering for registration.
     *
     * There is no destroy endpoint on purpose: enrollments reference an
     * offering with restrictOnDelete, so deleting one anybody had registered
     * for would either fail outright or orphan a transcript. Clearing
     * `is_active` takes it out of the registration list while every enrollment,
     * grade and timetable slot already attached to it stays intact.
     */
    public function update(UpdateCourseOfferingRequest $request, CourseOffering $offering): JsonResponse
    {
        $offering->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Course offering updated successfully.',
            'data' => new CourseOfferingResource($this->hydrate($offering)),
        ]);
    }

    /**
     * Load everything a single-offering response serialises.
     */
    private function hydrate(CourseOffering $offering): CourseOffering
    {
        $offering->loadCount(['enrollments' => fn ($query) => $query
            ->where('status', EnrollmentStatus::Active->value)]);

        return $offering->load(self::RESPONSE_RELATIONS);
    }
}
