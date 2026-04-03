<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

class IdGeneratorService
{
    protected array $studyTypeCodes = [
        'Undergraduate' => 'U',
        'Postgraduate'  => 'PG',
    ];

    public function generateStudentId(int $departmentId, string $studyType, int $entryYear): string
    {
        $department    = Department::with('faculty')->findOrFail($departmentId);
        $studyTypeCode = $this->studyTypeCodes[$studyType];
        $yearCode      = substr($entryYear, 2) . $studyTypeCode;

        do {
            $number = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $id     = "{$department->code}/{$yearCode}/{$number}";
        } while (User::where('student_id', $id)->exists());

        return $id;
    }

    public function generateStaffId(int $departmentId): string
    {
        $department = Department::with('faculty')->findOrFail($departmentId);
        $year       = now()->format('Y');

        do {
            $number = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $id     = "{$department->faculty->code}/{$department->code}/LEC/{$year}/{$number}";
        } while (User::where('staff_id', $id)->exists());

        return $id;
    }
}