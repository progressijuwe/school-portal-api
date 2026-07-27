<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'SOF')->firstOrFail();

        /** @var UserService $userService */
        $userService = app(UserService::class);

        $students = [
            [
                'name'        => 'Test Student One',
                'email'       => 'student1@aust.edu.ng',
                'department_id' => $department->id,
                'study_type'  => 'Undergraduate',
                'entry_year'  => 2022,
            ],
            [
                'name'        => 'Test Student Two',
                'email'       => 'student2@aust.edu.ng',
                'department_id' => $department->id,
                'study_type'  => 'Undergraduate',
                'entry_year'  => 2023,
            ],
        ];

        foreach ($students as $data) {
            $existing = \App\Models\User::where('email', $data['email'])->first();
            if ($existing) {
                $this->command->info("Skipped (exists): {$data['email']}");
                continue;
            }

            $data['role'] = 'student';
            $result = $userService->createUser($data);

            $result['user']->update([
                'password' => Hash::make('Password@123'),
                'must_change_password' => false,
            ]);

            $this->command->info(
                "Student seeded. Email: {$result['user']->email} | Student ID: {$result['user']->student_id} | Password: Password@123"
            );
        }
    }
}