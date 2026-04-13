<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\GpaRecord;
use App\Models\Grade;

class GradeService
{
    protected array $gradeScale = [
        ['grade' => 'A',  'min' => 95, 'max' => 100, 'points' => 4.00],
        ['grade' => 'A-', 'min' => 89, 'max' => 94,  'points' => 3.75],
        ['grade' => 'B+', 'min' => 83, 'max' => 88,  'points' => 3.25],
        ['grade' => 'B',  'min' => 77, 'max' => 82,  'points' => 3.00],
        ['grade' => 'B-', 'min' => 71, 'max' => 76,  'points' => 2.75],
        ['grade' => 'C+', 'min' => 65, 'max' => 70,  'points' => 2.25],
        ['grade' => 'C',  'min' => 59, 'max' => 64,  'points' => 2.00],
        ['grade' => 'C-', 'min' => 53, 'max' => 58,  'points' => 1.75],
        ['grade' => 'D',  'min' => 48, 'max' => 52,  'points' => 1.00],
        ['grade' => 'F',  'min' => 0,  'max' => 47,  'points' => 0.00],
    ];

    public function resolveGrade(float $score): array
    {
        foreach ($this->gradeScale as $scale) {
            if ($score >= $scale['min'] && $score <= $scale['max']) {
                return [
                    'letter_grade' => $scale['grade'],
                    'grade_point'  => $scale['points'],
                ];
            }
        }

        // Fallback — should never happen if score is 0-100
        return ['letter_grade' => 'F', 'grade_point' => 0.00];
    }

    public function computeAndStoreGpa(int $studentId, int $sessionId, string $semester): GpaRecord
    {
        // Get all approved grades for this student in this session/semester
        $approvedGrades = Enrollment::query()
            ->where('student_id', $studentId)
            ->whereHas('courseOffering', function ($q) use ($sessionId, $semester) {
                $q->where('academic_session_id', $sessionId)
                  ->where('semester', $semester);
            })
            ->with([
                'courseOffering.course:id,credit_units',
                'grade' => fn($q) => $q->where('status', 'approved'),
            ])
            ->get()
            ->filter(fn($enrollment) => $enrollment->grade !== null);

        $semesterCreditUnits  = 0;
        $semesterGradePoints  = 0;

        foreach ($approvedGrades as $enrollment) {
            $creditUnits          = $enrollment->courseOffering->course->credit_units;
            $semesterCreditUnits += $creditUnits;
            $semesterGradePoints += $creditUnits * $enrollment->grade->grade_point;
        }

        $gpa = $semesterCreditUnits > 0
            ? round($semesterGradePoints / $semesterCreditUnits, 2)
            : 0.00;

        // Get all previous approved GPA records for CGPA calculation
        $previousRecords = GpaRecord::where('student_id', $studentId)
            ->where(function ($q) use ($sessionId, $semester) {
                $q->where('academic_session_id', '!=', $sessionId)
                  ->orWhere(function ($q) use ($sessionId, $semester) {
                      $q->where('academic_session_id', $sessionId)
                        ->where('semester', '!=', $semester);
                  });
            })
            ->get();

        $cumulativeCreditUnits = $previousRecords->sum('total_credit_units') + $semesterCreditUnits;
        $cumulativeGradePoints = $previousRecords->sum('total_grade_points') + $semesterGradePoints;

        $cgpa = $cumulativeCreditUnits > 0
            ? round($cumulativeGradePoints / $cumulativeCreditUnits, 2)
            : 0.00;

        return GpaRecord::updateOrCreate(
            [
                'student_id'          => $studentId,
                'academic_session_id' => $sessionId,
                'semester'            => $semester,
            ],
            [
                'gpa'                      => $gpa,
                'cgpa'                     => $cgpa,
                'total_credit_units'       => $semesterCreditUnits,
                'total_grade_points'       => $semesterGradePoints,
                'cumulative_credit_units'  => $cumulativeCreditUnits,
                'cumulative_grade_points'  => $cumulativeGradePoints,
            ]
        );
    }
}