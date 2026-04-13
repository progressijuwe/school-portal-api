<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
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
            'data'    => $faculties,
        ]);
    }

    public function studyTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Study types retrieved successfully.',
            'data'    => [
                'Undergraduate',
                'Postgraduate',
            ],
        ]);
    }
    public function prefixes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Prefixes retrieved successfully.',
            'data'    => [
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
}