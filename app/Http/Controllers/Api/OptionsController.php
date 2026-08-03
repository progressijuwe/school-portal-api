<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;

class OptionsController extends Controller
{
    public function departments(): JsonResponse
    {
        $faculties = Faculty::with('departments:id,faculty_id,name,code')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully.',
            'data' => $faculties,
        ]);
    }

    public function studyTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Study types retrieved successfully.',
            'data' => [
                'Undergraduate',
                'Postgraduate',
            ],
        ]);
    }

    /**
     * The column list previously selected only id, name and is_current, so the
     * semester dates were absent and no caller could tell which half of the
     * year a session was in. Going through the resource ships the dates and the
     * derived `current_semester` alongside them.
     */
    public function academicSessions(): JsonResponse
    {
        $sessions = AcademicSession::orderByDesc('start_year')->get();

        return response()->json([
            'success' => true,
            'message' => 'Academic sessions retrieved successfully.',
            'data' => AcademicSessionResource::collection($sessions),
        ]);
    }

    public function prefixes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Prefixes retrieved successfully.',
            'data' => [
                'Dr.',
                'Prof.',
                'Mr.',
                'Mrs.',
                'Ms.',
                'Engr.',
                'Rev.',
            ],
        ]);
    }

    public function academicRules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Academic rules retrieved successfully.',
            'data' => [
                'min_credit_units_per_semester' => config('academics.min_credit_units_per_semester'),
                'max_credit_units_per_semester' => config('academics.max_credit_units_per_semester'),
            ],
        ]);
    }
}
