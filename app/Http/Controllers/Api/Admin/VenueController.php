<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends BaseController
{
    /**
     * Searching and filtering happen in the database.
     *
     * The previous version returned an unfiltered page of twenty, newest first,
     * which is unusable for finding one room in a campus-sized list. Ordering
     * by building then name groups rooms the way somebody looking for them
     * thinks about them.
     */
    public function index(Request $request): JsonResponse
    {
        $venues = Venue::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn ($query) => $query
                ->where('type', $request->string('type')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->orderBy('building')
            ->orderBy('name')
            ->paginate(perPage: min($request->integer('per_page', 20), 100))
            ->withQueryString();

        return $this->paginated($venues, VenueResource::class, 'Venues retrieved successfully.');
    }

    public function store(StoreVenueRequest $request): JsonResponse
    {
        // is_active is set explicitly rather than left to the column default, so
        // the model returned in the response carries the same value the row
        // does. Relying on the default meant the response said null and the
        // new venue looked inactive until the list was refetched.
        $venue = Venue::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Venue created successfully.',
            'data' => new VenueResource($venue),
        ], 201);
    }

    public function show(Venue $venue): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Venue retrieved successfully.',
            'data' => new VenueResource($venue),
        ]);
    }

    /**
     * There is no destroy endpoint on purpose: timetable slots reference a
     * venue with restrictOnDelete, so deleting a room anything was ever
     * scheduled in would fail outright. Clearing `is_active` takes it out of
     * circulation while the history that points at it stays intact.
     */
    public function update(UpdateVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully.',
            'data' => new VenueResource($venue),
        ]);
    }
}
