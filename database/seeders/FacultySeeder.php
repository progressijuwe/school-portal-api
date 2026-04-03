<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Faculty;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculties = [
            ['name' => 'Faculty of Engineering',               'code' => 'FENG'],
            ['name' => 'Faculty of Computing',                 'code' => 'FCOM'],
            ['name' => 'Faculty of Business',                  'code' => 'FBUS'],
            ['name' => 'Faculty of Law',                       'code' => 'FLAW'],
            ['name' => 'Faculty of Medicine & Health Sciences','code' => 'FMED'],
            ['name' => 'Faculty of Arts & Social Sciences',    'code' => 'FART'],
            ['name' => 'Faculty of Education',                 'code' => 'FEDU'],
            ['name' => 'Faculty of Environmental Sciences',    'code' => 'FENV'],
            ['name' => 'Faculty of Agriculture',               'code' => 'FAGR'],
        ];

        foreach ($faculties as $faculty) {
            Faculty::firstOrCreate(['code' => $faculty['code']], $faculty);
        }

        $this->command->info('Faculties seeded.');
    }
}
