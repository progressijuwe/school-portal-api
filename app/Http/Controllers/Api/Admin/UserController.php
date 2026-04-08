<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkImportUsersRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CsvImportService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        protected UserService    $userService,
        protected CsvImportService $csvImportService,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::with('department.faculty')
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['student', 'lecturer']))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data'    => UserResource::collection($users->items()),
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->userService->createUser($request->validated());

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->role) . ' account created successfully.',
            'data'    => new UserResource($result['user']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('department.faculty');

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data'    => new UserResource($user),
        ]);
    }

    public function bulkImport(BulkImportUsersRequest $request): JsonResponse
    {
        $role   = $request->role;
        $result = $this->csvImportService->parse($request->file('file'), $role);

        // If there was a structural error with the CSV itself
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        $created = [];
        $failed  = $result['invalid'];

        // Wrap all creations in a transaction
        DB::transaction(function () use ($result, $role, &$created) {
            foreach ($result['valid'] as $row) {
                $row['role'] = $role;
                $user        = $this->userService->createUser($row);
                $created[]   = new UserResource($user['user']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' user(s) imported successfully.',
            'data'    => [
                'created' => $created,
                'failed'  => $failed,
            ],
        ], 201);
    }

    public function downloadCsvTemplate(string $role): JsonResponse
    {
        if (! in_array($role, ['student', 'lecturer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role. Must be student or lecturer.',
            ], 422);
        }

        $headers = $role === 'student'
            ? ['name', 'email', 'department_id', 'study_type', 'entry_year']
            : ['name', 'email', 'department_id'];

        $example = $role === 'student'
            ? ['John Doe', 'john@uni.edu', '1', 'Undergraduate', '2022']
            : ['Dr. Jane Smith', 'jane@uni.edu', '1'];

        $filename = "{$role}_import_template.csv";
        $handle   = fopen('php://temp', 'r+');

        fputcsv($handle, $headers);
        fputcsv($handle, $example);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->json([
            'success'  => true,
            'message'  => 'CSV template generated.',
            'data'     => [
                'filename' => $filename,
                'headers'  => $headers,
                'content'  => base64_encode($csv),
            ],
        ]);
    }
}