<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $course_offering_id
 * @property EnrollmentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseOffering $courseOffering
 * @property-read Grade|null $grade
 * @property-read User|null $student
 *
 * @method static \Database\Factories\EnrollmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Enrollment forPeriod(int $sessionId, string $semester)
 * @method static Builder<static>|Enrollment newModelQuery()
 * @method static Builder<static>|Enrollment newQuery()
 * @method static Builder<static>|Enrollment occupyingSeat()
 * @method static Builder<static>|Enrollment query()
 * @method static Builder<static>|Enrollment whereCourseOfferingId($value)
 * @method static Builder<static>|Enrollment whereCreatedAt($value)
 * @method static Builder<static>|Enrollment whereId($value)
 * @method static Builder<static>|Enrollment whereStatus($value)
 * @method static Builder<static>|Enrollment whereStudentId($value)
 * @method static Builder<static>|Enrollment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_offering_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
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
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return HasOne<Grade, $this>
     */
    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    /**
     * Enrollments that hold a seat — pending approval or already active. These
     * are the ones that count toward a student's semester credit load.
     */
    public function scopeOccupyingSeat(Builder $query): Builder
    {
        return $query->whereIn('status', EnrollmentStatus::occupyingSeat());
    }

    /**
     * Restrict to a single academic session and semester via the offering.
     */
    public function scopeForPeriod(Builder $query, int $sessionId, string $semester): Builder
    {
        return $query->whereHas(
            'courseOffering',
            fn (Builder $offering) => $offering
                ->where('academic_session_id', $sessionId)
                ->where('semester', $semester)
        );
    }
}
