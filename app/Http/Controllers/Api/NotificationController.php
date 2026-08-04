<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notifications, for every authenticated role.
 *
 * The logic already lived in BaseController, but each role controller carried
 * its own three-line delegation to it — and admins were simply never given one.
 * They have been receiving `GradeSubmittedNotification` since grading was built
 * and had no endpoint to read it: the rows accumulated in the database
 * unreachable. One controller behind all three route groups means the next role
 * added cannot be forgotten the same way.
 */
class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->notificationResponse($request);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        return $this->markReadResponse($request, $id);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return $this->markAllReadResponse($request);
    }
}
