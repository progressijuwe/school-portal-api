<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreTimetableSlotRequest;
use App\Http\Requests\Admin\UpdateTimetableSlotRequest;
use App\Http\Resources\TimetableSlotResource;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends BaseController
{
    /**
     * Relations every slot response needs — a slot means nothing without the
     * course it schedules and the room it occupies.
     *
     * @var array<int, string>
     */
    private const RESPONSE_RELATIONS = [
        'courseOffering.course',
        'courseOffering.lecturer.lecturerProfile',
        'courseOffering.academicSession',
        'venue',
    ];

    /**
     * Searching and filtering happen in the database.
     *
     * The previous version paginated every slot ever created across every
     * session with no filters, which is the one thing a timetable screen cannot
     * be — an admin building next semester's schedule needs to see that
     * semester, one day at a time.
     */
    public function index(Request $request): JsonResponse
    {
        $slots = TimetableSlot::with(self::RESPONSE_RELATIONS)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->whereHas('courseOffering.course', fn ($course) => $course
                    ->where(fn ($inner) => $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")));
            })
            ->when($request->filled('session_id'), fn ($query) => $query
                ->whereHas('courseOffering', fn ($offering) => $offering
                    ->where('academic_session_id', $request->integer('session_id'))))
            ->when($request->filled('semester'), fn ($query) => $query
                ->whereHas('courseOffering', fn ($offering) => $offering
                    ->where('semester', $request->string('semester')->toString())))
            ->when($request->filled('day'), fn ($query) => $query
                ->where('day', $request->string('day')->toString()))
            ->when($request->filled('venue_id'), fn ($query) => $query
                ->where('venue_id', $request->integer('venue_id')))
            ->when($request->filled('lecturer_id'), fn ($query) => $query
                ->whereHas('courseOffering', fn ($offering) => $offering
                    ->where('lecturer_id', $request->integer('lecturer_id'))))
            ->when($request->filled('is_active'), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->orderByRaw("CASE day
                WHEN 'monday' THEN 1
                WHEN 'tuesday' THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday' THEN 4
                WHEN 'friday' THEN 5
            END")
            ->orderBy('start_time')
            // A week of slots for one cohort runs well past twenty rows, and a
            // timetable split across pages is not a timetable.
            ->paginate(perPage: min($request->integer('per_page', 50), 200))
            ->withQueryString();

        return $this->paginated($slots, TimetableSlotResource::class, 'Timetable retrieved successfully.');
    }

    public function store(StoreTimetableSlotRequest $request): JsonResponse
    {
        $slot = TimetableSlot::create($request->validated());
        $slot->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot created successfully.',
            'data' => new TimetableSlotResource($slot),
        ], 201);
    }

    public function show(TimetableSlot $slot): JsonResponse
    {
        $slot->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot retrieved successfully.',
            'data' => new TimetableSlotResource($slot),
        ]);
    }

    /**
     * Clashes are caught by TimetableService through the form request, which
     * checks the venue, the lecturer, and the cohort's own level — so a slot
     * that would double-book any of the three is rejected with a message
     * naming which.
     */
    public function update(UpdateTimetableSlotRequest $request, TimetableSlot $slot): JsonResponse
    {
        $slot->update($request->validated());
        $slot->load(self::RESPONSE_RELATIONS);

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot updated successfully.',
            'data' => new TimetableSlotResource($slot),
        ]);
    }

    public function destroy(TimetableSlot $slot): JsonResponse
    {
        $slot->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot deactivated successfully.',
        ]);
    }
}
