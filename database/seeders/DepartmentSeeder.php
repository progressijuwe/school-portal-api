<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Faculty;

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
                ['name' => 'Electrical & Electronics Engineering',  'code' => 'EEE'],
                ['name' => 'Mechanical Engineering',                'code' => 'MEE'],
                ['name' => 'Civil Engineering',                     'code' => 'CEE'],
                ['name' => 'Chemical Engineering',                  'code' => 'CHE'],
                ['name' => 'Petroleum Engineering',                 'code' => 'PET'],
                ['name' => 'Computer Engineering',                  'code' => 'COE'],

            ],

            // Faculty of Computing
            'FCOM' => [
                ['name' => 'Software Engineering',  'code' => 'SOF'],
                ['name' => 'Computer Science',      'code' => 'COS'],
                ['name' => 'Cyber Security',        'code' => 'CYB'],
                ['name' => 'Information Technology','code' => 'INT'],
            ],

            // Faculty of Business
            'FBUS' => [
                ['name' => 'Business Administration',   'code' => 'BUA'],
                ['name' => 'Accounting',                'code' => 'ACC'],
                ['name' => 'Economics',                 'code' => 'ECO'],
                ['name' => 'Finance',                   'code' => 'FIN'],
                ['name' => 'Marketing',                 'code' => 'MKT'],
            ],

            // Faculty of Law
            'FLAW' => [
                ['name' => 'Law', 'code' => 'LAW'],
            ],

            // Faculty of Medicine & Health Sciences
            'FMED' => [
                ['name' => 'Medicine & Surgery',    'code' => 'MDS'],
                ['name' => 'Nursing',               'code' => 'NUR'],
                ['name' => 'Pharmacy',              'code' => 'PHA'],
                ['name' => 'Public Health',         'code' => 'PBH'],
            ],

            // Faculty of Arts & Social Sciences
            'FART' => [
                ['name' => 'Mass Communication',                'code' => 'MLS'],
                ['name' => 'Psychology',                        'code' => 'PSY'],
                ['name' => 'Sociology',                         'code' => 'SOC'],
                ['name' => 'Political Science',                 'code' => 'POL'],
                ['name' => 'English & Literary Studies',        'code' => 'ELS'],
                ['name' => 'History & International Studies',   'code' => 'HIS'],
            ],

            // Faculty of Education
            'FEDU' => [
                ['name' => 'Education',                     'code' => 'EDU'],
                ['name' => 'Library & Information Science', 'code' => 'LIS'],
            ],

            // Faculty of Environmental Sciences
            'FENV' => [
                ['name' => 'Environmental Science',     'code' => 'EVS'],
                ['name' => 'Geography',                 'code' => 'GEO'],
                ['name' => 'Architecture',              'code' => 'ARC'],
                ['name' => 'Urban & Regional Planning', 'code' => 'URP'],
            ],

            // Faculty of Agriculture
            'FAGR' => [
                ['name' => 'Agriculture',               'code' => 'AGR'],
                ['name' => 'Forestry & Wildlife',       'code' => 'FWL'],
                ['name' => 'Food Science & Technology', 'code' => 'FST'],
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
                        'name'       => $dept['name'],
                        'code'       => $dept['code'],
                    ]
                );
            }
        }

        $this->command->info('Departments seeded.');
    }
}
