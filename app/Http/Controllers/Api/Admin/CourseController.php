<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends BaseController
{
    /**
     * Searching and filtering happen in the database.
     *
     * The admin courses page previously fetched everything and filtered in the
     * browser, which only works while the catalogue fits in one response.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = $this->filteredCourses($request)
            ->latest()
            ->paginate(perPage: min($request->integer('per_page', 12), 50))
            ->withQueryString();

        return $this->paginated($courses, CourseResource::class, 'Courses retrieved successfully.');
    }

    /**
     * Export the current catalogue view as CSV.
     *
     * Runs the same filter chain as the table and streams every match, so the
     * file cannot describe a narrower set than the screen it came from.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredCourses($request)->orderBy('code');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Without a BOM Excel reads the file in the system codepage and
            // mangles any non-ASCII course title.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'code', 'title', 'credit_units', 'level', 'semester', 'type',
                'department', 'faculty', 'status', 'lecturer', 'enrolled',
            ]);

            $query->chunk(200, function ($courses) use ($handle) {
                foreach ($courses as $course) {
                    $offering = $course->relationLoaded('offerings')
                        ? $course->offerings->first()
                        : null;

                    fputcsv($handle, [
                        $course->code,
                        $course->title,
                        $course->credit_units,
                        $course->level,
                        // Both are backed enums, so the backing value is what
                        // belongs in a CSV cell — the enum itself is not a
                        // scalar and would not survive the write.
                        $course->semester->value,
                        $course->type->value,
                        $course->department->name,
                        $course->department->faculty->name,
                        $course->is_active ? 'active' : 'inactive',
                        $offering?->lecturer?->name,
                        $offering !== null ? (int) $offering->enrollments_count : 0,
                    ]);
                }
            });

            fclose($handle);
        }, 'courses_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The filter chain the table and the export both run through, so the two
     * cannot drift into disagreeing about what is in scope.
     *
     * @return Builder<Course>
     */
    private function filteredCourses(Request $request): Builder
    {
        $session = $this->resolveSession($request);

        return Course::with([
            'department.faculty',
            // One offering per course — the one for the session being viewed —
            // carrying its lecturer and a live enrolment count. Limiting an
            // eager load like this is native in Laravel 12; without the limit
            // this would hydrate every offering a course has ever had.
            'offerings' => fn ($query) => $query
                ->when($session, fn ($inner) => $inner->where('academic_session_id', $session->id))
                ->withCount(['enrollments' => fn ($enrollment) => $enrollment
                    ->where('status', EnrollmentStatus::Active->value)])
                ->with('lecturer.lecturerProfile')
                ->latest('id')
                ->limit(1),
        ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn ($inner) => $inner
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"));
            })
            ->when($request->filled('department_id'), fn ($query) => $query
                ->where('department_id', $request->integer('department_id')))
            ->when($request->filled('faculty_id'), fn ($query) => $query
                ->whereHas('department', fn ($dept) => $dept
                    ->where('faculty_id', $request->integer('faculty_id'))))
            ->when($request->filled('level'), fn ($query) => $query
                ->where('level', $request->string('level')->toString()))
            ->when($request->filled('semester'), fn ($query) => $query
                ->where('semester', $request->string('semester')->toString()))
            ->when($request->filled('type'), fn ($query) => $query
                ->where('type', $request->string('type')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')));
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'data' => new CourseResource($course),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course retrieved successfully.',
            'data' => new CourseResource($course),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $course->update($request->validated());
        $course->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'data' => new CourseResource($course),
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
