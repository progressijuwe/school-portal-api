<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use Illuminate\Database\Seeder;

class AcademicSessionSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = [
            [
                'name' => '2023/2024',
                'start_year' => 2023,
                'end_year' => 2024,
                'first_semester_start' => '2023-09-11',
                'first_semester_end' => '2024-01-12',
                'second_semester_start' => '2024-02-26',
                'second_semester_end' => '2024-06-21',
                'is_current' => false,
            ],
            [
                'name' => '2024/2025',
                'start_year' => 2024,
                'end_year' => 2025,
                'first_semester_start' => '2024-09-09',
                'first_semester_end' => '2025-01-10',
                'second_semester_start' => '2025-02-24',
                'second_semester_end' => '2025-06-20',
                'is_current' => false,
            ],
            [
                'name' => '2025/2026',
                'start_year' => 2025,
                'end_year' => 2026,
                'first_semester_start' => '2025-09-15',
                'first_semester_end' => '2026-01-16',
                'second_semester_start' => '2026-03-03',
                'second_semester_end' => '2026-06-28',
                'is_current' => true,
            ],
        ];

        foreach ($sessions as $data) {
            AcademicSession::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('Academic sessions seeded.');
    }
}
