<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semester credit load
    |--------------------------------------------------------------------------
    |
    | Bounds enforced when a student submits a course registration, counting
    | every enrollment that holds a seat (pending or active) in the same
    | session and semester.
    |
    */

    'max_credit_units_per_semester' => env('MAX_CREDIT_UNITS_PER_SEMESTER', 24),
    'min_credit_units_per_semester' => env('MIN_CREDIT_UNITS_PER_SEMESTER', 15),

    /*
    |--------------------------------------------------------------------------
    | Grading scheme
    |--------------------------------------------------------------------------
    |
    | Maximum mark for each component of a course grade. These must sum to 100,
    | which is the range App\Services\GradeService::resolveGrade() maps onto a
    | letter grade. Stated here so the validation rules on every grading
    | endpoint read from one place instead of hardcoding 20 / 20 / 60.
    |
    */

    'max_ca_score' => env('MAX_CA_SCORE', 20),
    'max_project_score' => env('MAX_PROJECT_SCORE', 20),
    'max_exam_score' => env('MAX_EXAM_SCORE', 60),

];
