<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpaRecord extends Model
{
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'semester',
        'gpa',
        'cgpa',
        'total_credit_units',
        'total_grade_points',
        'cumulative_credit_units',
        'cumulative_grade_points',
    ];

    protected $casts = [
        'gpa'                     => 'decimal:2',
        'cgpa'                    => 'decimal:2',
        'total_grade_points'      => 'decimal:2',
        'cumulative_grade_points' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
