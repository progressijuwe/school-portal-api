<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AcademicSession;

class AcademicSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AcademicSession::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_year' => 2025,
                'end_year'   => 2026,
                'is_current' => true,
            ]
        );

        $this->command->info('Academic session seeded.');
    }
}
