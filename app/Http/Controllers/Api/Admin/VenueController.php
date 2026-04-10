<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    public function index(): JsonResponse
    {
        $venues = Venue::latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Venues retrieved successfully.',
            'data'    => VenueResource::collection($venues->items()),
            'meta'    => [
                'current_page' => $venues->currentPage(),
                'last_page'    => $venues->lastPage(),
                'per_page'     => $venues->perPage(),
                'total'        => $venues->total(),
            ],
        ]);
    }

    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = Venue::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venue created successfully.',
            'data'    => new VenueResource($venue),
        ], 201);
    }

    public function show(Venue $venue): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Venue retrieved successfully.',
            'data'    => new VenueResource($venue),
        ]);
    }

    public function update(UpdateVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully.',
            'data'    => new VenueResource($venue),
        ]);
    }
}