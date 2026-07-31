<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $academic_session_id
 * @property Semester $semester
 * @property numeric $gpa
 * @property numeric $cgpa
 * @property int $total_credit_units
 * @property numeric $total_grade_points
 * @property int $cumulative_credit_units
 * @property numeric $cumulative_grade_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicSession $academicSession
 * @property-read User|null $student
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereAcademicSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereCgpa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereCumulativeCreditUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereCumulativeGradePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereGpa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereTotalCreditUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereTotalGradePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GpaRecord whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class GpaRecord extends Model
{
    use HasFactory;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'gpa' => 'decimal:2',
            'cgpa' => 'decimal:2',
            'total_credit_units' => 'integer',
            'total_grade_points' => 'decimal:2',
            'cumulative_credit_units' => 'integer',
            'cumulative_grade_points' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
