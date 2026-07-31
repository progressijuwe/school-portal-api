<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['student', 'lecturer', 'admin'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        // Safe from the test suite too: $this->seed() dispatches through the
        // db:seed console command, so $this->command is always set. Output is
        // buffered by the test runner rather than printed per test.
        $this->command->info('Roles seeded.');
    }
}
