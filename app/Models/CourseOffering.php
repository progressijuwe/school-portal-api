<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $academic_session_id
 * @property int|null $lecturer_id
 * @property Semester $semester
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicSession $academicSession
 * @property-read Course|null $course
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read User|null $lecturer
 *
 * @method static \Database\Factories\CourseOfferingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereAcademicSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereLecturerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseOffering whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CourseOffering extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'academic_session_id',
        'lecturer_id',
        'semester',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations carry generics so static analysis can see through them.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
