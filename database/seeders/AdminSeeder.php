<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@aust.edu.ng'],
            [
                'name'     => 'Super Admin',
                'email'    => 'admin@aust.edu.ng',
                'password' => Hash::make('Admin@1234'),
            ]
        );

        $admin->assignRole('admin');

        $this->command->info('Admin seeded. Email: admin@aust.edu.ng | Password: Admin@1234');
    }
}