<?php

namespace App\Http\Controllers\Api;

use App\Enums\Semester;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseController extends Controller
{
    protected function resolveSession(Request $request): ?AcademicSession
    {
        if ($request->filled('session_id')) {
            return AcademicSession::find($request->integer('session_id'));
        }

        return AcademicSession::where('is_current', true)->first();
    }

    /**
     * The semester a request is asking about.
     *
     * Falls back to whichever semester the session is actually in today rather
     * than a hardcoded `first`, and ignores any value that is not a semester
     * this application recognises.
     */
    protected function resolveSemester(Request $request, ?AcademicSession $session = null): string
    {
        $requested = Semester::tryFrom((string) $request->query('semester'));

        if ($requested !== null) {
            return $requested->value;
        }

        return ($session?->currentSemester() ?? Semester::First)->value;
    }

    /**
     * Standard envelope for a paginated collection.
     *
     * This eight-line `meta` block was copy-pasted into six controllers, each
     * free to drift. One definition means the frontend can rely on the shape.
     *
     * Generic over the model so a paginator of any model is accepted —
     * PHP generics are invariant, so a bare `LengthAwarePaginator<int, Model>`
     * would reject `LengthAwarePaginator<int, User>`.
     *
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $extraMeta
     */
    protected function paginated(
        LengthAwarePaginator $paginator,
        string $resource,
        string $message,
        array $extraMeta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                ...$extraMeta,
            ],
        ]);
    }

    /**
     * Shorthand for a body-less success response.
     */
    protected function ok(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $status);
    }

    protected function notificationResponse(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    protected function markReadResponse(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($notificationId);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return $this->ok('Notification marked as read.');
    }

    protected function markAllReadResponse(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->ok('All notifications marked as read.');
    }
}
