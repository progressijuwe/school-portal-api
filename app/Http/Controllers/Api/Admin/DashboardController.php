<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Http\Controllers\Api\BaseController;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseController
{
    /**
     * Headline counters and the two approval queues.
     *
     * The frontend has been calling this route since the admin dashboard was
     * built; it never existed server-side, so every load 404'd.
     */
    public function index(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        $semester = $this->resolveSemester($request, $session);

        $cacheKey = 'admin.dashboard.stats.'
            .($session !== null ? $session->id : 'none')
            .'.'.$semester;

        // Short TTL: these are five COUNT queries on the busiest screen in the
        // app, and a minute of staleness on a headline counter is invisible.
        $stats = Cache::remember($cacheKey, now()->addMinute(), fn () => [
            'total_students' => User::role('student')->count(),
            'total_lecturers' => User::role('lecturer')->count(),
            'total_courses' => Course::where('is_active', true)->count(),

            /*
             * Both queues are counted exactly as their review screens group and
             * scope them — registrations by student, results by course offering,
             * both within the current session and semester.
             *
             * Counting raw rows across all sessions instead showed "64 pending"
             * on the dashboard next to a queue listing 16 items, and the badge
             * is a link straight to that queue.
             */
            'pending_registrations' => $session
                ? Enrollment::where('enrollments.status', EnrollmentStatus::Pending->value)
                    ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
                    ->where('course_offerings.academic_session_id', $session->id)
                    ->where('course_offerings.semester', $semester)
                    ->distinct()
                    ->count('enrollments.student_id')
                : 0,

            'pending_grades' => $session
                ? Grade::where('grades.status', GradeStatus::Pending->value)
                    ->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')
                    ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
                    ->where('course_offerings.academic_session_id', $session->id)
                    ->where('course_offerings.semester', $semester)
                    ->distinct()
                    ->count('enrollments.course_offering_id')
                : 0,

            // Not scoped to a session: being locked out is not an academic
            // event, and someone who cannot log in needs attention regardless
            // of which semester the portal is showing.
            'pending_password_resets' => User::whereNotNull('password_reset_requested_at')->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard statistics retrieved successfully.',
            'data' => $stats,
        ]);
    }

    /**
     * Recent administrative activity, newest first.
     *
     * Shaped to the `type` keys the frontend's ACTIVITY_CONFIG already knows
     * how to render: student, lecturer, course.
     */
    public function activity(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 5), 10);

        $users = User::query()
            ->with('department:id,name,code')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['student', 'lecturer']))
            ->with('roles:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => 'user-'.$user->id,
                'type' => $user->getRoleNames()->first() ?? 'student',
                'title' => $user->name,
                'meta' => array_filter([
                    $user->student_id ?? $user->staff_id,
                    $user->department?->code,
                ]),
                'label' => $user->hasRole('student')
                    ? 'New student enrolled'
                    : 'New lecturer added',
                'created_at' => $user->created_at,
            ]);

        $courses = Course::query()
            ->with('department:id,name,code')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Course $course) => [
                'id' => 'course-'.$course->id,
                'type' => 'course',
                'title' => $course->title,
                'meta' => array_filter([$course->code, $course->department->code]),
                'label' => 'New course created',
                'created_at' => $course->created_at,
            ]);

        $activity = $users->concat($courses)
            ->sortByDesc('created_at')
            ->take($limit)
            ->map(fn (array $entry) => [
                ...$entry,
                'time' => $entry['created_at']?->diffForHumans() ?? '',
                'created_at' => $entry['created_at']?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Recent activity retrieved successfully.',
            'data' => $activity,
        ]);
    }
}
