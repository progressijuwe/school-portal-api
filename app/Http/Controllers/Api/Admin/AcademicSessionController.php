<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreAcademicSessionRequest;
use App\Http\Requests\Admin\UpdateAcademicSessionRequest;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicSessionController extends BaseController
{
    /**
     * Every session, newest first, with the counts that say whether one is
     * safe to change.
     *
     * There is no destroy endpoint: course offerings and GPA records both
     * reference a session with restrictOnDelete, so a session anything was ever
     * run in cannot be removed. Sessions accumulate — that is the point of an
     * academic record.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = AcademicSession::withCount(['courseOfferings'])
            ->orderByDesc('start_year')
            ->orderByDesc('id')
            ->paginate(perPage: min($request->integer('per_page', 20), 100))
            ->withQueryString();

        return $this->paginated(
            $sessions,
            AcademicSessionResource::class,
            'Academic sessions retrieved successfully.'
        );
    }

    public function store(StoreAcademicSessionRequest $request): JsonResponse
    {
        // Never current on creation. Next year's session is normally set up
        // months before it begins, and silently switching the portal over to it
        // the moment it is saved would move every student's dashboard, course
        // list and registration window into a session that has not started.
        $session = AcademicSession::create([
            ...$request->validated(),
            'is_current' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Academic session created successfully.',
            'data' => new AcademicSessionResource($session),
        ], 201);
    }

    public function show(AcademicSession $session): JsonResponse
    {
        $session->loadCount('courseOfferings');

        return response()->json([
            'success' => true,
            'message' => 'Academic session retrieved successfully.',
            'data' => new AcademicSessionResource($session),
        ]);
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $session): JsonResponse
    {
        $session->update($request->validated());
        $session->loadCount('courseOfferings');

        return response()->json([
            'success' => true,
            'message' => 'Academic session updated successfully.',
            'data' => new AcademicSessionResource($session),
        ]);
    }

    /**
     * Roll the portal over to a different session.
     *
     * This is the single most consequential switch an administrator can throw:
     * dashboards, course lists, registration, grading and timetables all
     * resolve the current session first, so everyone's view of the system moves
     * at once. It is a dedicated endpoint rather than a field on update so it
     * cannot happen as a side effect of correcting a typo in a session name.
     */
    public function setCurrent(AcademicSession $session): JsonResponse
    {
        if ($session->is_current) {
            return response()->json([
                'success' => false,
                'message' => 'This is already the current academic session.',
            ], 409);
        }

        $session->markAsCurrent();

        return response()->json([
            'success' => true,
            'message' => "{$session->name} is now the current academic session.",
            'data' => new AcademicSessionResource($session->fresh()),
        ]);
    }
}
