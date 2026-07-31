<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'SOF')->firstOrFail();

        /** @var UserService $userService */
        $userService = app(UserService::class);

        $lecturers = [
            [
                'name' => 'Test Lecturer One',
                'email' => 'lecturer1@aust.edu.ng',
                'department_id' => $department->id,
                'prefix' => 'Dr.',
                'highest_qualification' => 'PhD',
                'specialization' => 'Software Engineering',
            ],
            [
                'name' => 'Test Lecturer Two',
                'email' => 'lecturer2@aust.edu.ng',
                'department_id' => $department->id,
                'prefix' => 'Mr.',
                'highest_qualification' => 'MSc',
                'specialization' => 'Cyber Security',
            ],
        ];

        foreach ($lecturers as $data) {
            $existing = User::where('email', $data['email'])->first();
            if ($existing) {
                $this->command->info("Skipped (exists): {$data['email']}");

                continue;
            }

            $data['role'] = 'lecturer';
            $result = $userService->createUser($data);

            $result['user']->update([
                'password' => Hash::make('Password@123'),
                'must_change_password' => false,
            ]);

            $this->command->info(
                "Lecturer seeded. Email: {$result['user']->email} | Staff ID: {$result['user']->staff_id} | Password: Password@123"
            );
        }
    }
}
