<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [

            // Faculty of Engineering
            'FENG' => [
                ['name' => 'Electrical & Electronics Engineering',  'code' => 'EEE', 'duration_years' => 5],
                ['name' => 'Mechanical Engineering',                'code' => 'MEE', 'duration_years' => 5],
                ['name' => 'Civil Engineering',                     'code' => 'CEE', 'duration_years' => 5],
                ['name' => 'Chemical Engineering',                  'code' => 'CHE', 'duration_years' => 5],
                ['name' => 'Petroleum Engineering',                 'code' => 'PET', 'duration_years' => 5],
                ['name' => 'Computer Engineering',                  'code' => 'COE', 'duration_years' => 5],

            ],

            // Faculty of Computing
            'FCOM' => [
                ['name' => 'Software Engineering',  'code' => 'SOF', 'duration_years' => 4],
                ['name' => 'Computer Science',      'code' => 'COS', 'duration_years' => 4],
                ['name' => 'Cyber Security',        'code' => 'CYB', 'duration_years' => 4],
                ['name' => 'Information Technology', 'code' => 'INT', 'duration_years' => 4],
            ],

            // Faculty of Business
            'FBUS' => [
                ['name' => 'Business Administration',   'code' => 'BUA', 'duration_years' => 4],
                ['name' => 'Accounting',                'code' => 'ACC', 'duration_years' => 4],
                ['name' => 'Economics',                 'code' => 'ECO', 'duration_years' => 4],
                ['name' => 'Finance',                   'code' => 'FIN', 'duration_years' => 4],
                ['name' => 'Marketing',                 'code' => 'MKT', 'duration_years' => 4],
            ],

            // Faculty of Law
            'FLAW' => [
                ['name' => 'Law', 'code' => 'LAW', 'duration_years' => 5],
            ],

            // Faculty of Medicine & Health Sciences
            'FMED' => [
                ['name' => 'Medicine & Surgery',    'code' => 'MDS', 'duration_years' => 6],
                ['name' => 'Nursing',               'code' => 'NUR', 'duration_years' => 5],
                ['name' => 'Pharmacy',              'code' => 'PHA', 'duration_years' => 5],
                ['name' => 'Public Health',         'code' => 'PBH', 'duration_years' => 4],
            ],

            // Faculty of Arts & Social Sciences
            'FART' => [
                ['name' => 'Mass Communication',                'code' => 'MLS', 'duration_years' => 4],
                ['name' => 'Psychology',                        'code' => 'PSY', 'duration_years' => 4],
                ['name' => 'Sociology',                         'code' => 'SOC', 'duration_years' => 4],
                ['name' => 'Political Science',                 'code' => 'POL', 'duration_years' => 4],
                ['name' => 'English & Literary Studies',        'code' => 'ELS', 'duration_years' => 4],
                ['name' => 'History & International Studies',   'code' => 'HIS', 'duration_years' => 4],
            ],

            // Faculty of Education
            'FEDU' => [
                ['name' => 'Education',                     'code' => 'EDU', 'duration_years' => 4],
                ['name' => 'Library & Information Science', 'code' => 'LIS', 'duration_years' => 4],
            ],

            // Faculty of Environmental Sciences
            'FENV' => [
                ['name' => 'Environmental Science',     'code' => 'EVS', 'duration_years' => 4],
                ['name' => 'Geography',                 'code' => 'GEO', 'duration_years' => 4],
                ['name' => 'Architecture',              'code' => 'ARC', 'duration_years' => 5],
                ['name' => 'Urban & Regional Planning', 'code' => 'URP', 'duration_years' => 5],
            ],

            // Faculty of Agriculture
            'FAGR' => [
                ['name' => 'Agriculture',               'code' => 'AGR', 'duration_years' => 4],
                ['name' => 'Forestry & Wildlife',       'code' => 'FWL', 'duration_years' => 4],
                ['name' => 'Food Science & Technology', 'code' => 'FST', 'duration_years' => 4],
            ],

        ];

        foreach ($departments as $facultyCode => $depts) {
            $faculty = Faculty::where('code', $facultyCode)->first();

            if (! $faculty) {
                $this->command->warn("Faculty with code {$facultyCode} not found. Skipping.");

                continue;
            }

            foreach ($depts as $dept) {
                Department::firstOrCreate(
                    ['code' => $dept['code']],
                    [
                        'faculty_id' => $faculty->id,
                        'name' => $dept['name'],
                        'code' => $dept['code'],
                        'duration_years' => $dept['duration_years'],
                    ]
                );
            }
        }

        $this->command->info('Departments seeded.');
    }
}
