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
        protected UserService      $userService,
        protected CsvImportService $csvImportService,
    ) {}

    public function index(): JsonResponse
    {
        $users = User::with('department.faculty', 'lecturerProfile')
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
        $result = $this->userService->createUser(
            $request->validated(),
            $request->file('photo')
        );

        $result['user']->load('department.faculty', 'profile', 'lecturerProfile');

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->role) . ' account created successfully.',
            'data'    => new UserResource($result['user']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('department.faculty', 'profile', 'lecturerProfile');

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

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        $created = [];
        $failed  = $result['invalid'];

        foreach ($result['valid'] as $index => $row) {
            $row['role'] = $role;

            try {
                DB::transaction(function () use ($row, &$created) {
                    $user      = $this->userService->createUser($row);
                    $created[] = new UserResource($user['user']);
                });
            } catch (\Throwable $e) {
                $failed[] = [
                    'row'    => $index + 2,
                    'data'   => $row,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

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
            : ['name', 'email', 'department_id', 'prefix', 'highest_qualification', 'specialization'];

        $example = $role === 'student'
            ? ['John Doe', 'john@uni.edu', '1', 'Undergraduate', '2022']
            : ['Jane Smith', 'jane@uni.edu', '1', 'Dr.', 'PhD Computer Science', 'Artificial Intelligence'];

        $filename = "{$role}_import_template.csv";
        $handle   = fopen('php://temp', 'r+');

        fputcsv($handle, $headers);
        fputcsv($handle, $example);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->json([
            'success' => true,
            'message' => 'CSV template generated.',
            'data'    => [
                'filename' => $filename,
                'headers'  => $headers,
                'content'  => base64_encode($csv),
            ],
        ]);
    }
}