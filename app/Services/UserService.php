<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct
    (
        protected IdGeneratorService $idGenerator
    ) {}

    public function createUser(array $data): array
    {
        $temporaryPassword = $this->generateTemporaryPassword();
        $role              = $data['role'];

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => Hash::make($temporaryPassword),
            'department_id'       => $data['department_id'],
            'study_type'          => $role === 'student' ? $data['study_type'] : null,
            'entry_year'          => $role === 'student' ? $data['entry_year'] : null,
            'must_change_password'=> true,
            'student_id'          => $role === 'student'
                                        ? $this->idGenerator->generateStudentId(
                                            $data['department_id'],
                                            $data['study_type'],
                                            $data['entry_year']
                                          )
                                        : null,
            'staff_id'            => $role === 'lecturer'
                                        ? $this->idGenerator->generateStaffId($data['department_id'])
                                        : null,
        ]);

        $user->assignRole($role);
        $user->notify(new UserRegisteredNotification($temporaryPassword, $role));
        $user->load('department.faculty');

        return [
            'user'  => $user,
            'password' => $temporaryPassword,
        ];
    }

    protected function generateTemporaryPassword(): string
    {
        // Generates a readable password like "Xk9#mP2q"
        return ucfirst(Str::random(4)) . rand(10, 99) . '!' . Str::random(3);
    }
}