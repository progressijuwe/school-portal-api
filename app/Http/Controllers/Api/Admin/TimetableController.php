<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTimetableSlotRequest;
use App\Http\Requests\Admin\UpdateTimetableSlotRequest;
use App\Http\Resources\TimetableSlotResource;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;

class TimetableController extends Controller
{
    public function index(): JsonResponse
    {
        $dayOrder = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5];

        $slots = TimetableSlot::with('courseOffering.course', 'courseOffering.lecturer', 'venue')
            ->orderByRaw("CASE day 
                WHEN 'monday' THEN 1 
                WHEN 'tuesday' THEN 2 
                WHEN 'wednesday' THEN 3 
                WHEN 'thursday' THEN 4 
                WHEN 'friday' THEN 5 
            END")
            ->orderBy('start_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Timetable retrieved successfully.',
            'data' => TimetableSlotResource::collection($slots->items()),
            'meta' => [
                'current_page' => $slots->currentPage(),
                'last_page' => $slots->lastPage(),
                'per_page' => $slots->perPage(),
                'total' => $slots->total(),
            ],
        ]);
    }

    public function store(StoreTimetableSlotRequest $request): JsonResponse
    {
        $slot = TimetableSlot::create($request->validated());
        $slot->load('courseOffering.course', 'courseOffering.lecturer', 'venue');

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot created successfully.',
            'data' => new TimetableSlotResource($slot),
        ], 201);
    }

    public function show(TimetableSlot $slot): JsonResponse
    {
        $slot->load('courseOffering.course', 'courseOffering.lecturer', 'venue');

        return response()->json([
            'success' => true,
            'message' => 'Timetable slot retrieved successfully.',
            'data' => new TimetableSlotResource($slot),
        ]);
    }

    public function update(UpdateTimetableSlotRequest $request, TimetableSlot $slot): JsonResponse
    {
        $slot->update($request->validated());
        $slot->load('courseOffering.course', 'courseOffering.lecturer', 'venue');

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
